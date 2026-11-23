<?php
// pages/p_iacr_frontmatter.php -- LNCS front matter as one LaTeX file (/iacr_frontmatter)
// Part of the IACR extensions; not part of upstream HotCRP.
// Replaces iacr/getFrontMatter.php. Papers appear in iacr.org order under a
// single placeholder topic; /iacr_lncs_frontmatter uses the LNCS editor's
// arrangement instead.

require_once(__DIR__ . "/iacr/finalpapers.php");
require_once(__DIR__ . "/iacr/frontmatter.php");

class IACR_Frontmatter_Page {
    /** @var Conf */
    protected $conf;

    function __construct(Conf $conf) {
        $this->conf = $conf;
    }

    static function go(Contact $user, Qrequest $qreq) {
        IACR_Page::check_chair($user, $qreq);
        (new static($user->conf))->print_latex();
    }

    /** @return IACR_FinalPapers */
    protected function final_papers_source() {
        return new IACR_FinalPapers($this->conf);
    }

    function print_latex() {
        Navigation::header("Content-Type: text/plain; charset=utf-8");
        (new IACR_FrontMatter($this->conf))->print_organization();
        echo "% Create chapters for the table of contents and reorder things.\n";
        IACR_FrontMatter::print_section("Add Topics");
        $papers = $this->final_papers_source()->final_papers();
        if ($papers === null) {
            echo "% Unable to fetch final versions from www.iacr.org\n";
        } else {
            $pageno = 1;
            IACR_FrontMatter::print_papers($papers, $pageno);
        }
        IACR_FrontMatter::print_end();
    }
}
