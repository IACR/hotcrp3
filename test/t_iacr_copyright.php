<?php
// t_iacr_copyright.php -- HotCRP tests for IACR's fork: the /iacrcopyright
// page. Not part of upstream HotCRP.

class IACR_TestCopyright_Page extends IacrCopyright_Page {
    /** @var list<array<string,mixed>> */
    public $stored = [];
    /** @var list<array{string,string}> */
    public $mailed = [];

    protected function store_signed($row) {
        $this->stored[] = $row;
        return true;
    }

    protected function send_mail($to, $subject, $body, $headers) {
        $this->mailed[] = [$to, $subject];
    }
}

class IACR_Copyright_Tester {
    /** @var Conf
     * @readonly */
    public $conf;
    /** @var PaperInfo */
    private $prow;
    /** @var Contact */
    private $u_author;
    /** @var Contact */
    private $u_pc;

    function __construct(Conf $conf) {
        $this->conf = $conf;
        $this->prow = $conf->checked_paper_by_id(1);
        $aid = $conf->fetch_ivalue("select contactId from PaperConflict where paperId=1 and conflictType>=? order by contactId limit 1", CONFLICT_AUTHOR);
        $this->u_author = $this->signed_in_user($conf->user_by_id($aid)->email);
        foreach ($conf->pc_members() as $u) {
            if (!$u->privChair
                && !$this->prow->has_conflict($u)
                && $u->can_view_paper($this->prow)) {
                $this->u_pc = $this->signed_in_user($u->email);
                break;
            }
        }
    }

    /** @param string $email
     * @return Contact */
    private function signed_in_user($email) {
        $u = $this->conf->fresh_user_by_email($email);
        return $u->activate(TestQreq::get(), false);
    }

    /** @param Qrequest $qreq
     * @return array{IACR_TestCopyright_Page,string} */
    private function run_page($qreq) {
        $page = new IACR_TestCopyright_Page($qreq->user(), $qreq);
        Qrequest::set_main_request($qreq);
        ob_start();
        try {
            $page->run();
        } finally {
            $out = ob_get_clean();
        }
        return [$page, $out];
    }

    /** @return array<string,string> */
    private function signed_args() {
        return [
            "c" => "update",
            "contact" => "A. Author <a@example.org>",
            "signedBy1" => "A. Author",
            "signedBy2" => "A. Author"
        ];
    }

    private function option_value() {
        return $this->conf->fetch_ivalue("select value from PaperOption where paperId=1 and optionId=?", PaperOption::IACR_COPYRIGHT_ID);
    }

    function test_page_is_a_component() {
        xassert(!!$this->u_author);
        xassert(!!$this->u_pc);
        $qreq = TestQreq::get_page("iacrcopyright/1")->set_user($this->u_author);
        $pc = $this->conf->page_components($this->u_author, $qreq);
        $gj = $pc->get("iacrcopyright");
        xassert(!!$gj);
        xassert_eqq($gj->print_function, "IacrCopyright_Page::go");
        xassert(!file_exists(SiteLoader::$root . "/iacrcopyright/index.php"));
    }

    function test_get_shows_form() {
        $this->conf->set_opt("iacrType", "tosc");
        $qreq = TestQreq::get_page("iacrcopyright/1")->set_user($this->u_author);
        list($page, $out) = $this->run_page($qreq);
        xassert_str_contains($out, "IACR Transactions on Symmetric Cryptology");
        xassert_str_contains($out, htmlspecialchars($this->prow->title));
        xassert_str_contains($out, "iacrcopyright/1/submit");
        xassert_str_contains($out, "post=");
        xassert(!str_contains($out, '[$'));
        xassert_eqq($page->stored, []);
    }

    function test_unknown_type() {
        $this->conf->set_opt("iacrType", null);
        $qreq = TestQreq::get_page("iacrcopyright/1")->set_user($this->u_author);
        list($page, $out) = $this->run_page($qreq);
        xassert_str_contains($out, "unknown iacrType");
        $this->conf->set_opt("iacrType", "rump");
        list($page, $out) = $this->run_page($qreq);
        xassert_str_contains($out, "rump doesn&#039;t use copyright form");
    }

    function test_non_author_rejected() {
        $this->conf->set_opt("iacrType", "tosc");
        $qreq = TestQreq::get_page("iacrcopyright/1")->set_user($this->u_pc);
        list($page, $out) = $this->run_page($qreq);
        xassert_str_contains($out, "Only an author");
        $qreq = TestQreq::post_page("iacrcopyright/1/submit", $this->signed_args())->set_user($this->u_pc);
        list($page, $out) = $this->run_page($qreq);
        xassert_str_contains($out, "Only an author");
        xassert_eqq($page->stored, []);
    }

    function test_bad_paper() {
        $this->conf->set_opt("iacrType", "tosc");
        $qreq = TestQreq::get_page("iacrcopyright/9999")->set_user($this->u_author);
        list($page, $out) = $this->run_page($qreq);
        xassert_str_contains($out, "are you sure you have permission");
    }

    function test_post_requires_token() {
        $this->conf->set_opt("iacrType", "tosc");
        $qreq = TestQreq::unapproved_post($this->signed_args())
            ->set_page("iacrcopyright")->set_path("/1/submit")
            ->set_user($this->u_author);
        list($page, $out) = $this->run_page($qreq);
        xassert_str_contains($out, "session has expired");
        xassert_eqq($page->stored, []);
        xassert_eqq($this->option_value(), null);
    }

    function test_post_validation() {
        $this->conf->set_opt("iacrType", "tosc");
        $args = $this->signed_args();
        unset($args["signedBy2"]);
        $qreq = TestQreq::post_page("iacrcopyright/1/submit", $args)->set_user($this->u_author);
        list($page, $out) = $this->run_page($qreq);
        xassert_str_contains($out, "You must sign section 2 or section 3");
        xassert_eqq($page->stored, []);

        $args = $this->signed_args() + ["signedBy3" => "Gov"];
        $qreq = TestQreq::post_page("iacrcopyright/1/submit", $args)->set_user($this->u_author);
        list($page, $out) = $this->run_page($qreq);
        xassert_str_contains($out, "not both");
        xassert_eqq($page->stored, []);
    }

    function test_post_signs() {
        $this->conf->set_opt("iacrType", "tosc");
        $qreq = TestQreq::post_page("iacrcopyright/1/submit", $this->signed_args())->set_user($this->u_author);
        list($page, $out) = $this->run_page($qreq);
        xassert_str_contains($out, "successfully submitted");
        xassert_str_contains($out, "/paper/1/edit");
        xassert_eqq(count($page->stored), 1);
        $row = $page->stored[0];
        xassert_eqq($row["paperId"], 1);
        xassert_eqq($row["signedBy1"], "A. Author");
        xassert_eqq($row["signedBy2"], "A. Author");
        xassert_eqq($row["signedBy3"], null);
        xassert_str_contains($row["form"], 'value="A. Author"');
        xassert_str_contains($row["form"], "A. Author &lt;a@example.org&gt;");
        xassert_eqq(count($page->mailed), 1);
        xassert_eqq($page->mailed[0][0], "copyrightform@iacr.org");
        xassert_eqq($this->option_value(), 1);

        $this->conf->qe("delete from PaperOption where paperId=1 and optionId=?", PaperOption::IACR_COPYRIGHT_ID);
        $this->conf->set_opt("iacrType", null);
    }
}
