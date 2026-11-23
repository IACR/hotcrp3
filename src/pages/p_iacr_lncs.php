<?php
// pages/p_iacr_lncs.php -- LNCS proceedings preparation (/iacr_lncs)
// Part of the IACR extensions; not part of upstream HotCRP.
// Replaces iacr/lncs.php.

require_once(__DIR__ . "/iacr/finalpapers.php");

class IACR_Lncs_Page {
    /** @var Conf */
    protected $conf;

    function __construct(Conf $conf) {
        $this->conf = $conf;
    }

    static function go(Contact $user, Qrequest $qreq) {
        IACR_Page::check_chair($user, $qreq);
        (new static($user->conf))->print_page($qreq);
    }

    /** @return IACR_FinalPapers */
    protected function final_papers_source() {
        return new IACR_FinalPapers($this->conf);
    }

    /** Print a warning listing accepted papers with no final version, if
     * any. Returns true if there were any.
     * @param string $klass extra CSS classes for the warning
     * @return bool */
    static function print_missing_final_versions(IACR_FinalPapers $fps, $klass = "") {
        if (!($missing = $fps->missing_final_versions())) {
            return false;
        }
        echo '<div class="alert alert-warning', $klass === "" ? "" : " {$klass}", '"><p>Warning: the following accepted papers appear to have not uploaded their final versions yet:</p><ul>';
        foreach ($missing as $p) {
            echo '<li><a href="', htmlspecialchars($fps->conf()->hoturl("paper", ["p" => $p["paperId"]])), '">',
                htmlspecialchars($p["title"]), '</a></li>';
        }
        echo "</ul></div>\n";
        return true;
    }

    function print_page(Qrequest $qreq) {
        $fps = $this->final_papers_source();
        $db = $this->conf->opt("dbName") ?? "final";
        IACR_Page::print_header($qreq, "IACR functionality of HotCRP", "lncs");
        echo "<h3>LNCS Preparation</h3>\n";
        if (!self::print_missing_final_versions($fps)) {
            $editor_url = htmlspecialchars($this->conf->hoturl("iacr_lncs_editor"));
            echo '<div class="alert alert-success">All final versions have been uploaded.</div>
<p>
  Once you have all of the final versions uploaded, you can proceed to create
  the material required by Springer for the LNCS volumes of the proceedings.
  Preparation of the Springer LNCS volumes should follow the instructions
  provided by Springer, which have in the past been located at
  <a href="https://www.springer.com/gp/computer-science/lncs/editor-guidelines-for-springer-proceedings">this URL</a>. Note that IACR uses their own copyright
  forms, so you should ignore the LNCS instructions for Copyright.
</p>
<p>
  The instructions for preparation of the LNCS volume(s) requires
  several items:
</p>
<ol>
  <li><a style="font-weight: 600;" target="_blank" href="', htmlspecialchars($fps->api_url("download")), '" download="', htmlspecialchars("{$db}.zip"), '">Download Zip archive</a> with a bundle of all final versions of papers.</li>
  <li><a style="font-weight: 600" target="_blank" href="', htmlspecialchars($this->conf->hoturl("iacr_author_emails")), '" download="author_emails.tsv">TSV spreadsheet of author emails</a> and disambiguated names of authors (in a spreadsheet TSV)
  </li>
  <li><a style="font-weight:600" href="', $editor_url, '">Create the front matter</a> for the LNCS volume(s) (in a LaTeX file).
    This contains the program committee, external reviewers, and table of contents. This requires
    you to group papers into topics and volumes to obey the 500 page limit. For
    this purpose we have created a <a style="font-weight:600" href="', $editor_url, '">drag-and-drop tool</a>
    to simplify the task. If you prefer, you can perform this step manually starting from a
    <a target="_blank" href="', htmlspecialchars($this->conf->hoturl("iacr_frontmatter")), '" download="frontmatter.tex">single LaTeX file</a>.
    You can also download a <a target="_blank" href="', htmlspecialchars($this->conf->hoturl("iacr_final_json")), '" download="final_papers.json">JSON file of metadata for the final papers.</a>
  </li>
</ol>
';
        }
        IACR_Page::print_footer();
    }
}
