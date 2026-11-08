<?php
// iacr_register_venue.php -- register this HotCRP instance with IACR's AICheck server
//
// Creates a read-only API token for an administrator of this instance, then
// sends it with the venue details to AICheck's HMAC-signed POST /api/1.0/venue
// (see webapp/api.py in the aicheck repository). AICheck creates the venue,
// or updates it if it exists, and then fetches this instance's settings.
//
// The API base URL is $Opt["aicheckUrl"], e.g.
// "https://aicheck.iacr.org/api/1.0/", and the shared secret is
// $Opt["aicheckSecret"], both set in conf/options.php. For testing against
// another server, --api-url and --secret-file override them; the secret is
// never accepted on the command line, which other users can see.
//
// If AICheck does not accept the request, the new token is deleted again.

if (realpath($_SERVER["PHP_SELF"]) === __FILE__) {
    require_once(dirname(__DIR__) . "/src/init.php");
    exit(IACRRegisterVenue_Batch::make_args($argv)->run());
}

class IACRRegisterVenue_Batch {
    const DEFAULT_USER = "hotcrpadmin@iacr.org";
    const DEFAULT_SCOPE = "submission:read other:read settings:read";

    /** @var Conf */
    public $conf;
    /** @var string */
    private $email;
    /** @var string */
    private $pcchair_email;
    /** @var string */
    private $api_url;
    /** @var ?string */
    private $secret_file;
    /** @var string */
    private $hotcrp_key;
    /** @var string */
    private $venue_name;
    /** @var int|string|null */
    private $deadline;
    /** @var ?string */
    private $status;
    /** @var string */
    private $scope;
    /** @var int */
    private $expiry;
    /** @var bool */
    private $dry_run;

    function __construct(Conf $conf, $arg) {
        $this->conf = $conf;
        $this->email = $arg["user"] ?? self::DEFAULT_USER;
        // Not Conf::site_contact(): when contactEmail is unset it falls back to
        // one chair's own address, and AICheck needs the alias for all chairs.
        $this->pcchair_email = trim($arg["pcchair-email"] ?? (string) ($conf->opt("contactEmail") ?? ""));
        if ($this->pcchair_email === "" || $this->pcchair_email === "you@example.com") {
            throw new CommandLineException("Set \$Opt[\"contactEmail\"] to the PC chairs' alias, or use `--pcchair-email`");
        } else if (!filter_var($this->pcchair_email, FILTER_VALIDATE_EMAIL)) {
            throw new CommandLineException("PC chair email `{$this->pcchair_email}` is not a valid email address");
        }
        $this->api_url = rtrim($arg["api-url"] ?? $conf->opt("aicheckUrl") ?? "", "/");
        if (!preg_match('/\Ahttps?:\/\//', $this->api_url)) {
            throw new CommandLineException("`--api-url` or \$Opt[\"aicheckUrl\"] must be an http(s) URL");
        }
        $this->secret_file = $arg["secret-file"] ?? null;
        // IACR instances run at https://submit.iacr.org/<shortName>, and AICheck
        // builds this instance's URL as HOTCRP_BASE_URL/hotcrp_key.
        $this->hotcrp_key = $arg["hotcrp-key"] ?? (string) $conf->short_name;
        if (!preg_match('/\A[A-Za-z0-9_]{3,32}\z/', $this->hotcrp_key)) {
            throw new CommandLineException("Venue key `{$this->hotcrp_key}` must be 3-32 characters from A-Za-z0-9_; check \$Opt[\"shortName\"] or use `--hotcrp-key`");
        }
        $this->venue_name = $arg["venue-name"] ?? ($conf->long_name ?: $conf->short_name);
        if (isset($arg["deadline"])) {
            $d = $arg["deadline"];
            $this->deadline = ctype_digit($d) ? (int) $d : $d; // Unix time or ISO 8601
        } else {
            $sub = (int) $conf->setting("sub_sub");
            $this->deadline = $sub > 0 ? $sub : null;
        }
        $this->status = $arg["status"] ?? null;
        $this->scope = $arg["scope"] ?? self::DEFAULT_SCOPE;
        $s = $arg["expiry"] ?? "365d";
        if (strcasecmp($s, "never") === 0) {
            $this->expiry = -1;
        } else if (($n = stonum($s)) !== null) {
            $this->expiry = (int) ($n * 86400);
        } else if (($n = SettingParser::parse_duration($s)) !== null) {
            $this->expiry = (int) $n;
        } else {
            throw new CommandLineException("Bad `--expiry`");
        }
        $this->dry_run = isset($arg["dry-run"]);
    }

    /** @return int */
    function run() {
        $user = $this->conf->user_by_email($this->email);
        if (!$user) {
            throw new CommandLineException("User {$this->email} not found");
        } else if (!$user->privChair) {
            throw new CommandLineException("{$this->email} is not an administrator of this instance");
        }

        $venue = [
            "hotcrp_key" => $this->hotcrp_key,
            "name" => $this->venue_name,
            "submission_deadline" => $this->deadline,
            "pcchair_email" => $this->pcchair_email,
            "creator_email" => $user->email,
            "creator_name" => $user->name() !== "" ? $user->name() : null
        ];
        if ($this->status !== null) {
            $venue["status"] = $this->status;
        }
        $venue = array_filter($venue, function ($v) { return $v !== null; });

        if ($this->dry_run) {
            $venue["hotcrp_api_token"] = "(a new token would be created here)";
            fwrite(STDOUT, "POST {$this->api_url}/venue\n"
                   . json_encode($venue, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
            return 0;
        }

        $secret = $this->secret();
        $token = Authorization_Token::prepare_bearer($user, $this->expiry);
        $token->change_data("note", "IACR AICheck")
            ->change_data("scope", $this->scope);
        $token->insert();
        if (!$token->stored()) {
            throw new CommandLineException("Could not create token");
        }
        $venue["hotcrp_api_token"] = $token->salt;

        [$status, $body] = self::post_signed("{$this->api_url}/venue", $venue, $secret);
        $result = json_decode($body ?? "", true);
        if ($status !== 200 && $status !== 201) {
            $token->delete();
            $why = is_array($result) ? ($result["error"] ?? $body) : ($body ?? "no response");
            fwrite(STDERR, "AICheck rejected the venue (HTTP {$status}): {$why}\nThe new token was deleted.\n");
            return 1;
        }
        $what = !empty($result["created"]) ? "Created" : "Updated";
        fwrite(STDOUT, "{$what} venue {$venue["hotcrp_key"]} at {$this->api_url}\n");
        return 0;
    }

    /** The shared secret: from --secret-file if given, else $Opt["aicheckSecret"].
     * @return string */
    private function secret() {
        if ($this->secret_file !== null) {
            return self::read_secret($this->secret_file);
        }
        $secret = trim((string) ($this->conf->opt("aicheckSecret") ?? ""));
        if ($secret === "") {
            throw new CommandLineException("\$Opt[\"aicheckSecret\"] is not set");
        }
        return $secret;
    }

    /** @param string $path
     * @return string */
    static function read_secret($path) {
        $secret = @file_get_contents($path);
        if ($secret === false || ($secret = trim($secret)) === "") {
            throw new CommandLineException("Cannot read secret from {$path}");
        }
        return $secret;
    }

    /** The signature AICheck expects: "sha256=" + hex HMAC-SHA256 of "<timestamp>." + body.
     * @param string $secret
     * @param string $timestamp
     * @param string $body
     * @return string */
    static function sign($secret, $timestamp, $body) {
        return "sha256=" . hash_hmac("sha256", "{$timestamp}.{$body}", $secret);
    }

    /** POST $data as JSON with AICheck's signature headers.
     * @param string $url
     * @param array $data
     * @param string $secret
     * @return array{int,?string} HTTP status (0 if no response) and body */
    static function post_signed($url, $data, $secret) {
        $body = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $timestamp = (string) time();
        $context = stream_context_create(["http" => [
            "method" => "POST",
            "header" => "Content-Type: application/json\r\n"
                . "X-AICheck-Timestamp: {$timestamp}\r\n"
                . "X-AICheck-Signature: " . self::sign($secret, $timestamp, $body) . "\r\n",
            "content" => $body,
            "timeout" => 30,
            "ignore_errors" => true  // return the body of 4xx/5xx responses too
        ]]);
        $response = @file_get_contents($url, false, $context);
        $headers = function_exists("http_get_last_response_headers")
            ? (http_get_last_response_headers() ?? [])
            : ($http_response_header ?? []);
        $status = 0;
        if (!empty($headers) && preg_match('/\AHTTP\/\S+\s+(\d+)/', $headers[0], $m)) {
            $status = (int) $m[1];
        }
        return [$status, $response === false ? null : $response];
    }

    /** @return IACRRegisterVenue_Batch */
    static function make_args($argv) {
        $arg = (new Getopt)->long(
            "name:,n: !",
            "config: !",
            "help,h !",
            "user:,u: =EMAIL Administrator who gets the token; becomes the venue's creator [" . self::DEFAULT_USER . "]",
            "pcchair-email: =EMAIL Alias that reaches all PC chairs [\$Opt[\"contactEmail\"]]",
            "api-url: =URL AICheck API base URL [\$Opt[\"iacrWebhookUrl\"]]",
            "secret-file: =FILE Read the shared secret from FILE [\$Opt[\"aicheckSecret\"]]",
            "hotcrp-key: =KEY Venue key; the last part of this instance's URL [\$Opt[\"shortName\"]]",
            "venue-name: =NAME Venue name [\$Opt[\"longName\"]]",
            "deadline: =TIME Submission deadline, Unix time or ISO 8601 [submission deadline setting, if set]",
            "status: =STATUS draft, active, or archived [draft for a new venue]",
            "scope: =SCOPE Token scope [" . self::DEFAULT_SCOPE . "]",
            "expiry:,e: =DURATION Token expiration [365d]",
            "dry-run Show what would be sent; create no token"
        )->description("Register this HotCRP instance as a venue on IACR's AICheck server.
Usage: php batch/iacr_register_venue.php [-n CONFID|--config CONFIG] [OPTIONS]")
         ->helpopt("help")
         ->maxarg(0)
         ->parse($argv);

        $conf = initialize_conf($arg["config"] ?? null, $arg["name"] ?? null);
        return new IACRRegisterVenue_Batch($conf, $arg);
    }
}
