<?php
// iacr/user_webhook.php -- notify AICheck when an account joins or leaves the
// program committee, or gains or loses the PC chair role. Newly created
// accounts count: creating a PC member sends `"created": true`.
//
// The following are required in conf/options.php:
//   require_once(__DIR__ . "/../iacr/user_webhook.php");
//   $Opt["hooks"]["user_roles_changed"][] = "iacr_webhook_user_roles_changed";
//   $Opt["aicheckUrl"]    = "https://aicheck.iacr.org/api/1.0/";   // POSTs to webhook/user under this
//   $Opt["aicheckSecret"] = "...";     // HMAC-SHA256 key shared with the receiver
//
// Requires the core patches that call the "user_roles_changed" hook from
// UserStatus::save_user() (profile page, bulk user upload, /api/account,
// batch/saveusers.php) and UserActions::change_roles() (the "Add to PC" and
// "Remove from PC" actions on the user list).
//
// The body carries the account's full new roles, not only the change, so the
// receiver can set its state from it and a retried or repeated event is harmless.

/** @param int $old_roles
 * @param int $new_roles
 * @param ?Contact $actor
 * @param bool $created */
function iacr_webhook_user_roles_changed(Contact $user, $old_roles, $new_roles, $actor, $created) {
    $conf = $user->conf;
    $mask = Contact::ROLE_ANYPC | Contact::ROLE_CHAIR;
    if ((($old_roles ^ $new_roles) & $mask) === 0
        || $user->is_bot()   // bots are unlisted PC members by default
        || !($url = $conf->opt("aicheckUrl"))
        || !($secret = $conf->opt("aicheckSecret"))) {
        return;
    }
    $body = json_encode([
        "event" => "user_roles_changed",
        "conference" => $conf->opt("shortName"),
        "email" => $user->email,
        "name" => $user->name() !== "" ? $user->name() : null,
        "affiliation" => $user->affiliation !== "" ? $user->affiliation : null,
        "roles" => UserStatus::unparse_roles_json($new_roles & Contact::ROLE_DBMASK) ?? [],
        "old_roles" => UserStatus::unparse_roles_json($old_roles & Contact::ROLE_DBMASK) ?? [],
        "created" => $created,
        "actor_email" => $actor ? $actor->email : null,
        "time" => Conf::$now
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    IACRUserWebhook::queue(rtrim($url, "/") . "/webhook/user", $secret, $body);
}

class IACRUserWebhook {
    /** @var list<string> */
    static private $bodies = [];
    /** @var ?string */
    static private $url;
    /** @var ?string */
    static private $secret;

    /** Bulk PC changes can call the hook many times in one request, so the
     * events are sent together after the response is flushed (under PHP-FPM).
     * @param string $url
     * @param string $secret
     * @param string $body */
    static function queue($url, $secret, $body) {
        if (empty(self::$bodies)) {
            register_shutdown_function([self::class, "send_all"]);
        }
        self::$url = $url;
        self::$secret = $secret;
        self::$bodies[] = $body;
    }

    static function send_all() {
        if (function_exists("fastcgi_finish_request")) {
            fastcgi_finish_request();
        }
        $bodies = self::$bodies;
        self::$bodies = [];
        foreach ($bodies as $i => $body) {
            $ts = (string) time();
            $ch = curl_init(self::$url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_HTTPHEADER => [
                    "Content-Type: application/json",
                    "X-AICheck-Timestamp: {$ts}",
                    "X-AICheck-Signature: sha256=" . hash_hmac("sha256", "{$ts}.{$body}", self::$secret)
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_TIMEOUT => 5
            ]);
            curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            if ($code >= 200 && $code < 300) {
                continue;
            }
            $email = json_decode($body)->email ?? "?";
            error_log("user_webhook {$email}: notify failed: " . ($error ?: "HTTP {$code}"));
            if ($code === 0 && ($n = count($bodies) - $i - 1) > 0) {
                // AICheck is unreachable; don't wait out a timeout for each event
                error_log("user_webhook: {$n} more events not sent");
                return;
            }
        }
    }
}
