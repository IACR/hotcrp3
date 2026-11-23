<?php
// pages/p_iacr_lncsfrontmatter.php -- LNCS front matter from the editor (/iacr_lncs_frontmatter)
// Part of the IACR extensions; not part of upstream HotCRP.
// Replaces iacr/createFrontMatter.php. The table of contents follows the
// chair's saved arrangement in the LNCS editor (/iacr_lncs_editor).

require_once(__DIR__ . "/iacr/frontmatter.php");
require_once(__DIR__ . "/iacr/lncswork.php");

class IACR_LncsFrontmatter_Page {
    /** @var Contact */
    protected $user;

    function __construct(Contact $user) {
        $this->user = $user;
    }

    static function go(Contact $user, Qrequest $qreq) {
        IACR_Page::check_chair($user, $qreq);
        (new static($user))->print_latex();
    }

    /** @return IACR_LncsWork */
    protected function work() {
        return new IACR_LncsWork($this->user);
    }

    function print_latex() {
        Navigation::header("Content-Type: text/plain; charset=utf-8");
        if (($lncs = $this->work()->load_data()) === null) {
            Navigation::http_response_code(404);
            echo "No saved work from the LNCS editor. Arrange the papers in the LNCS editor first.\n";
            return;
        }
        (new IACR_FrontMatter($this->user->conf))->print_organization();
        $pageno = 1;
        foreach ($lncs["volumes"] ?? [] as $volume) {
            foreach ($volume["topics"] ?? [] as $topic) {
                IACR_FrontMatter::print_section($topic["name"] ?? "");
                IACR_FrontMatter::print_papers($topic["papers"] ?? [], $pageno);
            }
        }
        if (!empty($lncs["unassigned_papers"])) {
            IACR_FrontMatter::print_section("Uncategorized papers");
            IACR_FrontMatter::print_papers($lncs["unassigned_papers"], $pageno);
        }
        IACR_FrontMatter::print_end();
    }
}
