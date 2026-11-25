<?php
// pages/p_iacrcopyright.php -- IACR copyright/license form for a submission
// Part of the IACR extensions; not part of upstream HotCRP.
//
// GET  /iacrcopyright/PID          shows the form for paper PID
// POST /iacrcopyright/PID/submit   records the signed form
//
// Signed forms are stored in a separate copyright database whose
// credentials live in /var/www/util/hotcrp/copyright_db.php. On success,
// the paper's IACR copyright field (PaperOption::IACR_COPYRIGHT_ID) is set.

class IacrCopyright_Page {
    /** @var Conf */
    protected $conf;
    /** @var Contact */
    protected $user;
    /** @var Qrequest */
    protected $qreq;
    /** @var ?PDO */
    private $db;

    const COPYRIGHT_DB_CONFIG = "/var/www/util/hotcrp/copyright_db.php";

    function __construct(Contact $user, Qrequest $qreq) {
        $this->conf = $user->conf;
        $this->user = $user;
        $this->qreq = $qreq;
    }

    static function go(Contact $user, Qrequest $qreq) {
        (new IacrCopyright_Page($user, $qreq))->run();
    }

    function run() {
        // Find the paper named in the path. As on the paper page,
        // PaperRequest checks that the user can view it and redirects
        // signed-out users to sign in.
        $pr = PaperRequest::make($this->qreq, false);
        if ($pr instanceof Redirection) {
            throw $pr;
        } else if ($pr instanceof FailureReason) {
            $this->print_error("An error has occurred - are you sure you have permission?");
            return;
        }
        $prow = $pr->prow;
        // Only the paper's authors (or an administrator) may sign.
        if ($prow->paperId <= 0
            || (!$prow->has_author($this->user) && !$this->user->allow_admin($prow))) {
            $this->print_error("Only an author of this submission can sign its copyright form.");
            return;
        }

        if (!($form = $this->form_info())) {
            return;
        }

        $copyright = $this->render_form($prow, $form);
        if (!$this->qreq->is_post()) {
            // Authors may resubmit a form they already signed.
            echo $copyright;
            return;
        }

        if (!$this->qreq->valid_post()) {
            $this->print_error("Your session has expired. Please reload the copyright form and try again.");
            return;
        }
        $this->handle_submit($prow, $form, $copyright);
    }

    /** Choose the copyright form for this venue, based on `$Opt["iacrType"]`.
     *
     * On success, returns a pair `[TEMPLATE, SUBSTITUTIONS]`: TEMPLATE is
     * the file name of an HTML form in `src/pages/iacrcopyright/`, and
     * SUBSTITUTIONS maps template placeholders (such as `[$longName]`) to
     * venue-specific text. `render_form()` adds the paper- and user-specific
     * placeholders. Journals and CiC name the venue through SUBSTITUTIONS;
     * the conference form needs none.
     *
     * If the venue type has no copyright form (`rump`, `rwc`), or `iacrType`
     * is unset or unrecognized, prints an error page and returns null.
     *
     * @return ?array{string,array<string,string>} */
    protected function form_info() {
        $type = $this->conf->opt("iacrType");
        switch ($type) {
        case "tosc":
            return ["journal_copyright.html", [
                '[$longName]' => "IACR Transactions on Symmetric Cryptology",
                '[$shortName]' => "ToSC"
            ]];
        case "tches":
            return ["journal_copyright.html", [
                '[$longName]' => "IACR Transactions on Cryptographic Hardware and Embedded Systems",
                '[$shortName]' => "TCHES"
            ]];
        case "cic":
            return ["cic_agreement.html", [
                '[$longName]' => "IACR Communications in Cryptology",
                '[$shortName]' => "CiC"
            ]];
        case "crypto":
        case "eurocrypt":
        case "asiacrypt":
        case "tcc":
        case "pkc":
            return ["conference_copyright.html", []];
        case "rump":
        case "rwc":
            // TODO: fix this for video and slides.
            $this->print_error("{$type} doesn't use copyright form");
            return null;
        default:
            $this->print_error("unknown iacrType");
            return null;
        }
    }

    /** Return `authorInformation` without the optional ORCID iD fields, so
     * the form and the stored record are unchanged by ORCID iDs.
     * @return string */
    static function authors_without_orcids(PaperInfo $prow) {
        return preg_replace('/^((?:[^\t\n]*\t){3}[^\t\n]*)\t[^\n]*$/m', '$1', $prow->authorInformation ?? "");
    }

    /** @param array{string,array<string,string>} $form
     * @return string */
    private function render_form(PaperInfo $prow, $form) {
        $action = $this->conf->hoturl("iacrcopyright/{$prow->paperId}/submit",
                                      ["c" => "update"], Conf::HOTURL_POST);
        $subst = $form[1] + [
            '[$action]' => htmlspecialchars($action),
            '[$title]' => htmlspecialchars($prow->title),
            '[$confName]' => htmlspecialchars($this->conf->opt("longName") ?? ""),
            '[$authors]' => htmlspecialchars(self::authors_without_orcids($prow)),
            '[$date]' => date('F j, Y'),
            '[$year]' => date('Y'),
            '[$subId]' => (string) $prow->paperId,
            '[$correspondingAuthor]' => htmlspecialchars($this->user->firstName . ' ' . $this->user->lastName)
        ];
        $copyright = file_get_contents(__DIR__ . "/iacrcopyright/" . $form[0]);
        return str_replace(array_keys($subst), array_values($subst), $copyright);
    }

    /** @param array{string,array<string,string>} $form
     * @param string $copyright */
    private function handle_submit(PaperInfo $prow, $form, $copyright) {
        $qreq = $this->qreq;
        $contact = trim($qreq->contact ?? "");
        $signedBy1 = trim($qreq->signedBy1 ?? "");
        $signedBy2 = trim($qreq->signedBy2 ?? "");
        $signedBy3 = trim($qreq->signedBy3 ?? "");
        $agency = trim($qreq->agency ?? "");
        $country = trim($qreq->country ?? "");

        if ($contact === "") {
            $this->print_error("You must fill in the name and address of the corresponding author.");
            return;
        } else if ($signedBy1 === "") {
            $this->print_error("You must sign section 1");
            return;
        } else if ($signedBy2 === "" && $signedBy3 === "") {
            $this->print_error("You must sign section 2 or section 3");
            return;
        } else if ($signedBy2 !== "" && $signedBy3 !== "") {
            $this->print_error("You must sign only one of section 2 or section 3 (not both)");
            return;
        }
        if ($signedBy3 === "") {
            // non-government author
            $agency = $country = "";
        } else if ($agency === "") {
            $this->print_error("You must fill in the government agency name.");
            return;
        } else if ($country === "") {
            $this->print_error("You must fill in the country name.");
            return;
        }

        // Fill the submitted values into the form to record it as the
        // author saw it.
        $document = new DOMDocument();
        if (!@$document->loadHTML($copyright)) {
            $this->print_error("An error occurred in processing the form.");
            return;
        }
        $values = ["signedBy1" => $signedBy1, "signedBy2" => $signedBy2,
                   "signedBy3" => $signedBy3, "agency" => $agency,
                   "country" => $country];
        foreach ($values as $id => $v) {
            if ($v !== "" && ($node = $document->getElementById($id))) {
                $node->setAttribute("value", $v);
            }
        }
        if (($node = $document->getElementById("contact"))) {
            $node->nodeValue = "";
            $node->appendChild($document->createTextNode($contact));
        }
        $signed_form = $document->saveHTML();

        $shortName = $this->conf->opt("shortName");
        $row = [
            "paperId" => $prow->paperId,
            "shortName" => $shortName,
            "longName" => $this->conf->opt("longName"),
            "title" => $prow->title,
            "authorInformation" => self::authors_without_orcids($prow),
            "corresponding_author" => $contact,
            "form" => $signed_form,
            "signedBy1" => $signedBy1,
            "signedBy2" => $signedBy2 === "" ? null : $signedBy2,
            "signedBy3" => $signedBy3 === "" ? null : $signedBy3,
            "agency" => $agency === "" ? null : $agency,
            "country" => $country === "" ? null : $country
        ];
        try {
            if (!$this->store_signed($row)) {
                $this->print_error("Unable to save the copyright form.");
                return;
            }
        } catch (PDOException $e) {
            $this->print_error("Database error: " . $e->getMessage());
            return;
        }

        // Mark the paper's IACR copyright field as signed.
        $this->conf->qe("replace into PaperOption set paperId=?, optionId=?, value=1",
                        $prow->paperId, PaperOption::IACR_COPYRIGHT_ID);

        echo "<html><body><h3>Your copyright form was successfully submitted</h3>",
            "<p><strong><a href=\"", htmlspecialchars($this->conf->hoturl("paper", ["p" => $prow->paperId, "m" => "edit"])),
            "\">Return</a></strong></p></body></html>";

        // Email a copy to IACR. If this fails, the database still has a copy.
        $subject = "[{$shortName}] Copyright signed for: " . $prow->title;
        $emailFrom = $this->conf->opt("emailFrom");
        $headers = "MIME-Version: 1.0\r\n"
            . "Content-type: text/html; charset=utf-8\r\n"
            . "From: {$emailFrom}\r\n"
            . "Reply-To: {$emailFrom}\r\n"
            . "X-Mailer: PHP/" . phpversion() . "\r\n";
        $this->send_mail("copyrightform@iacr.org", $subject, $signed_form, $headers);
    }

    /** @param string $msg */
    protected function print_error($msg) {
        echo "<html><body><h3>", htmlspecialchars($msg), "</h3></body></html>";
    }


    // Copyright database. Tests override these methods.

    /** @return PDO */
    private function db() {
        if (!$this->db) {
            $dbname = $dbuser = $dbpassword = null;
            require(self::COPYRIGHT_DB_CONFIG);
            $this->db = new PDO("mysql:host=localhost;dbname={$dbname};charset=utf8", $dbuser, $dbpassword);
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
        return $this->db;
    }

    /** @param array<string,mixed> $row
     * @return bool */
    protected function store_signed($row) {
        $cols = array_keys($row);
        $sql = "REPLACE INTO copyright (" . join(",", $cols) . ") VALUES ("
            . join(",", array_map(function ($c) { return ":{$c}"; }, $cols)) . ")";
        return $this->db()->prepare($sql)->execute($row);
    }

    /** @param string $to
     * @param string $subject
     * @param string $body
     * @param string $headers */
    protected function send_mail($to, $subject, $body, $headers) {
        mail($to, $subject, $body, $headers);
    }
}
