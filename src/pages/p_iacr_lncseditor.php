<?php
// pages/p_iacr_lncseditor.php -- arrange papers into LNCS volumes (/iacr_lncs_editor)
// Part of the IACR extensions; not part of upstream HotCRP.
// Replaces iacr/lncsEditor.php. The page itself is the template
// src/pages/iacr/lncseditor.inc; it loads and saves the chair's work through
// /iacr_lncs_data and downloads LaTeX from /iacr_lncs_frontmatter.

require_once(__DIR__ . "/iacr/finalpapers.php");

class IACR_LncsEditor_Page {
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

    function print_missing_final_versions() {
        if (!IACR_Lncs_Page::print_missing_final_versions($this->final_papers_source(), "mt-5")) {
            echo '<div class="mt-5 mx-3 alert alert-success alert-dismissible">All final versions have been uploaded. Create your LNCS proceedings by adding topics to the volumes,
    and dragging papers from the left into the topics. You can also move topics between volumes. Each volume should not exceed 500 pages.<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>', "\n";
        }
    }

    function print_page(Qrequest $qreq) {
        $conf = $this->conf;
        $siteurl = htmlspecialchars($qreq->navigation()->siteurl());
        $home_url = htmlspecialchars($conf->hoturl("index"));
        $short_name = htmlspecialchars($conf->opt("shortName") ?? "");
        $frontmatter_url = htmlspecialchars($conf->hoturl("iacr_lncs_frontmatter"));
        // Handlebars fills in {{paperId}}
        $paper_url = str_replace("PAPERID", "{{paperId}}",
            htmlspecialchars($conf->hoturl("paper", ["p" => "PAPERID"])));
        // JavaScript values; json_encode escapes "/", so "</script>" can't occur
        $data_url_js = json_encode($conf->hoturl("iacr_lncs_data"));
        $post_js = json_encode($qreq->post_value());
        include(__DIR__ . "/iacr/lncseditor.inc");
    }
}
