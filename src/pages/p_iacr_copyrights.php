<?php
// pages/p_iacr_copyrights.php -- list submitted copyright forms (/iacr_copyrights)
// Part of the IACR extensions; not part of upstream HotCRP.
// Replaces iacr/copyright.php. Authors sign the forms at /iacrcopyright
// (src/pages/p_iacrcopyright.php), which stores them in the IACR
// copyright database.

class IACR_Copyrights_Page {
    /** @var Conf */
    protected $conf;

    function __construct(Conf $conf) {
        $this->conf = $conf;
    }

    static function go(Contact $user, Qrequest $qreq) {
        IACR_Page::check_chair($user, $qreq);
        (new static($user->conf))->print_page($qreq);
    }

    /** Return the papers of this conference with signed copyright forms, in
     * database order, or null if the copyright database could not be read.
     * @return ?list<array{paperId:mixed,title:string}> */
    protected function signed_copyrights() {
        if (!is_readable(IacrCopyright_Page::COPYRIGHT_DB_CONFIG)) {
            error_log("IACR copyright database: missing " . IacrCopyright_Page::COPYRIGHT_DB_CONFIG);
            return null;
        }
        try {
            require(IacrCopyright_Page::COPYRIGHT_DB_CONFIG);
            '@phan-var string $dbname';
            '@phan-var string $dbuser';
            '@phan-var string $dbpassword';
            $db = new PDO("mysql:host=localhost;dbname={$dbname};charset=utf8", $dbuser, $dbpassword);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $stmt = $db->prepare("select paperId, title from copyright where shortName=:dbName");
            $stmt->execute([":dbName" => $this->conf->opt("dbName")]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("IACR copyright database: " . $e->getMessage());
            return null;
        }
    }

    function print_page(Qrequest $qreq) {
        $copyrights = $this->signed_copyrights();
        $search_url = $this->conf->hoturl("search", ["q" => "", "t" => "acc"]) . "#view";
        IACR_Page::print_header($qreq, "IACR functionality of HotCRP", "copyright");
        echo '<h3>Submitted copyright forms</h3>
<p>
  Once you open the papers for final submission, the authors will see an
  IACR copyright form to be filled out. The papers listed below have had
  copyright submitted, but you should check which ones do <strong>not</strong>
  have copyright by
  <a href="', htmlspecialchars($search_url), '">searching for accepted papers</a>
  and checking "IACR Copyright Agreement" in the options.
</p>
';
        if ($copyrights === null) {
            echo '<p class="alert alert-danger">Unable to read the copyright database.</p>', "\n";
        } else if (empty($copyrights)) {
            echo "<p>No copyright forms have been submitted.</p>\n";
        } else {
            echo "<ol>\n";
            foreach ($copyrights as $paper) {
                echo "<li>", htmlspecialchars($paper["title"] ?? ""), "</li>\n";
            }
            echo "</ol>\n";
        }
        IACR_Page::print_footer();
    }
}
