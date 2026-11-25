<?php
// t_iacr_orcid.php -- HotCRP tests for IACR's fork: ORCID iDs stored as an
// optional fifth tab-separated field of authorInformation. Not part of
// upstream HotCRP.

require_once(__DIR__ . "/t_iacr_pages.php");

class IACR_Orcid_Tester {
    /** @var Conf
     * @readonly */
    public $conf;
    /** @var Contact
     * @readonly */
    public $u_root;
    /** @var ?int */
    private $pid;

    const O1 = "0000-0002-1825-0097"; // Josiah Carberry, ORCID's test iD
    const O2 = "0000-0001-5109-3700";
    const O3 = "0000-0002-1694-233X";

    function __construct(Conf $conf) {
        $this->conf = $conf;
        $this->u_root = $conf->root_user();
    }

    function test_clean() {
        xassert_eqq(Orcid::clean(self::O1), self::O1);
        xassert_eqq(Orcid::clean(" 0000000218250097 "), self::O1);
        xassert_eqq(Orcid::clean("https://orcid.org/0000-0002-1694-233x"), self::O3);
        xassert_eqq(Orcid::clean("http://www.orcid.org/0000-0002-1694-233X/"), self::O3);
        xassert_eqq(Orcid::clean("orcid.org/" . self::O2), self::O2);
        xassert_eqq(Orcid::clean(""), "");
        xassert_eqq(Orcid::clean(null), "");
        xassert_eqq(Orcid::clean("0000-0002-1825-0098"), null);  // bad check digit
        xassert_eqq(Orcid::clean("0000-0002-1825"), null);
        xassert_eqq(Orcid::clean(self::O1 . "?"), null);
        xassert_eqq(Orcid::clean("https://example.com/" . self::O1), null);
        xassert_eqq(Orcid::clean("0000-0002-1825-0097\t"), self::O1);
        xassert_eqq(Orcid::clean("0000-0002\t-1825-0097"), null);
        xassert(Orcid::is_canonical(self::O1));
        xassert(!Orcid::is_canonical("0000000218250097"));
        xassert(!Orcid::is_canonical(null));
    }

    function test_tabbed_round_trip() {
        // four-field lines are unchanged
        foreach (["A\tB\ta@b.c\tUniv", "A\tB\t\t", "\t\ta@b.c\t"] as $line) {
            $au = Author::make_tabbed($line);
            xassert_eqq($au->orcid, "");
            xassert(!$au->has_orcid());
            xassert_eqq($au->unparse_tabbed(), $line);
        }
        // short lines are padded to four fields, as before
        xassert_eqq(Author::make_tabbed("A\tB\ta@b.c")->unparse_tabbed(), "A\tB\ta@b.c\t");
        // an empty fifth field is dropped
        xassert_eqq(Author::make_tabbed("A\tB\ta@b.c\tUniv\t")->unparse_tabbed(), "A\tB\ta@b.c\tUniv");
        // five-field lines keep the ORCID iD
        $line = "Josiah\tCarberry\tj@b.c\tBrown\t" . self::O1;
        $au = Author::make_tabbed($line);
        xassert_eqq($au->firstName, "Josiah");
        xassert_eqq($au->affiliation, "Brown");
        xassert_eqq($au->orcid, self::O1);
        xassert($au->has_orcid());
        xassert_eqq($au->unparse_tabbed(), $line);
        // the ORCID field does not affect name/email/affiliation identity
        xassert($au->nea_equals(Author::make_tabbed("Josiah\tCarberry\tj@b.c\tBrown")));
        // a value that would break the format is never written
        $au->orcid = "x\ty";
        xassert_eqq($au->unparse_tabbed(), "Josiah\tCarberry\tj@b.c\tBrown");
        $au->orcid = "x\ny";
        xassert_eqq($au->unparse_tabbed(), "Josiah\tCarberry\tj@b.c\tBrown");
    }

    function test_parse_author_list() {
        $aus = PaperInfo::parse_author_list("A\tB\ta@b.c\tX\t" . self::O1 . "\nC\tD\tc@d.e\tY\n");
        xassert_eqq(count($aus), 2);
        xassert_eqq($aus[0]->orcid, self::O1);
        xassert_eqq($aus[1]->orcid, "");
        xassert_eqq($aus[1]->author_index, 2);
    }

    function test_assign_keyed() {
        $au = Author::make_keyed(["name" => "Josiah Carberry", "email" => "j@b.c", "orcid" => "https://orcid.org/0000000218250097"]);
        xassert_eqq($au->orcid, self::O1);
        xassert_eqq(Author::make_keyed(["name" => "J C", "orcidid" => self::O2])->orcid, self::O2);
        xassert_eqq(Author::make_keyed(["name" => "J C", "ORCID" => self::O3])->orcid, self::O3);
        // missing key: not supplied
        xassert_eqq(Author::make_keyed(["name" => "J C"])->orcid, null);
        xassert_eqq(Author::make_keyed((object) ["name" => "J C", "orcid" => null])->orcid, null);
        // explicitly empty
        xassert_eqq(Author::make_keyed(["name" => "J C", "orcid" => ""])->orcid, "");
        xassert_eqq(Author::make_keyed(["name" => "J C", "orcid" => "  "])->orcid, "");
        // invalid values are kept (whitespace-simplified) for validation
        xassert_eqq(Author::make_keyed(["name" => "J C", "orcid" => "0000-0002-1825-0098"])->orcid, "0000-0002-1825-0098");
        xassert_eqq(Author::make_keyed(["name" => "J C", "orcid" => "a\tb"])->orcid, "a b");
        // non-strings are treated as empty
        xassert_eqq(Author::make_keyed(["name" => "J C", "orcid" => 12])->orcid, "");
        // other constructors leave it unsupplied
        xassert_eqq(Author::make_nae("J", "C", "j@b.c", "")->orcid, null);
        xassert_eqq(Author::make_string("J C <j@b.c>")->orcid, null);
    }

    function test_merge() {
        $saved = Author::make_tabbed("Josiah\tCarberry\tj@b.c\tBrown\t" . self::O1);
        // not supplied: filled from the saved author
        $au = Author::make_nae("Josiah", "Carberry", "j@b.c", "Brown");
        $au->merge($saved);
        xassert_eqq($au->orcid, self::O1);
        // explicitly empty: stays empty
        $au = Author::make_keyed(["email" => "j@b.c", "orcid" => ""]);
        $au->merge($saved);
        xassert_eqq($au->orcid, "");
        xassert_eqq($au->firstName, "Josiah");
        // supplied: kept
        $au = Author::make_keyed(["email" => "j@b.c", "orcid" => self::O2]);
        $au->merge($saved);
        xassert_eqq($au->orcid, self::O2);
        // never copied from an account
        $u = $this->conf->checked_user_by_email("estrin@usc.edu");
        $au = Author::make_email("estrin@usc.edu");
        $au->merge($u);
        xassert_eqq($au->orcid, null);
    }

    /** @return PaperInfo */
    private function paper() {
        return $this->conf->checked_paper_by_id($this->pid, $this->u_root);
    }

    /** @return list<?string> */
    private function orcids() {
        return array_map(function ($au) { return $au->orcid; }, $this->paper()->author_list());
    }

    function test_save_json() {
        $ps = new PaperStatus($this->u_root);
        $ps->save_paper_json((object) [
            "id" => "new", "title" => "ORCID test paper", "abstract" => "Abstract",
            "authors" => [
                (object) ["name" => "Josiah Carberry", "email" => "carberry@_.com", "orcid" => "https://orcid.org/" . self::O1],
                (object) ["name" => "Laurel Haak", "email" => "haak@_.com", "orcid" => self::O2],
                (object) ["name" => "No Email"]
            ]
        ]);
        xassert_paper_status($ps);
        $this->pid = $ps->paperId;
        xassert_eqq($this->orcids(), [self::O1, self::O2, ""]);
        xassert_eqq($this->paper()->authorInformation,
            "Josiah\tCarberry\tcarberry@_.com\t\t" . self::O1 . "\n"
            . "Laurel\tHaak\thaak@_.com\t\t" . self::O2 . "\n"
            . "No\tEmail\t\t");

        // JSON without `orcid` keys keeps saved iDs (matched by email)
        $ps = new PaperStatus($this->u_root);
        $ps->save_paper_json((object) [
            "id" => $this->pid,
            "authors" => [
                (object) ["name" => "Laurel Haak", "email" => "haak@_.com"],
                (object) ["name" => "Josiah Carberry", "email" => "carberry@_.com"],
                (object) ["name" => "No Email", "orcid" => self::O3]
            ]
        ]);
        xassert_paper_status($ps);
        xassert_eqq($this->orcids(), [self::O2, self::O1, self::O3]);

        // explicit empty clears; other iDs untouched
        $ps = new PaperStatus($this->u_root);
        $ps->save_paper_json((object) [
            "id" => $this->pid,
            "authors" => [
                (object) ["name" => "Laurel Haak", "email" => "haak@_.com", "orcid" => ""],
                (object) ["name" => "Josiah Carberry", "email" => "carberry@_.com"],
                (object) ["name" => "No Email", "orcid" => self::O3]
            ]
        ]);
        xassert_paper_status($ps);
        xassert_eqq($this->orcids(), ["", self::O1, self::O3]);
        ConfInvariants::test_all($this->conf);
    }

    function test_save_web_keeps_orcids() {
        // the current web form has no ORCID inputs; saving it, even with
        // reordered authors, keeps iDs for authors with email
        $ps = new PaperStatus($this->u_root);
        xassert($ps->prepare_save_paper_web(new Qrequest("POST", [
            "has_authors" => "1",
            "authors:1:name" => "Josiah Carberry", "authors:1:email" => "carberry@_.com",
            "authors:2:name" => "Laurel Haak", "authors:2:email" => "haak@_.com",
            "authors:3:name" => "New Author", "authors:3:email" => "new@_.com"
        ]), $this->paper()));
        xassert_paper_status($ps);
        xassert($ps->execute_save());
        xassert_eqq($this->orcids(), [self::O1, "", ""]);

        // unrelated web save leaves authorInformation unchanged
        $before = $this->paper()->authorInformation;
        $ps = new PaperStatus($this->u_root);
        xassert($ps->prepare_save_paper_web(new Qrequest("POST", ["title" => "ORCID test paper 2"]), $this->paper()));
        xassert($ps->execute_save());
        xassert_eqq($this->paper()->authorInformation, $before);
    }

    /** @param array<string,string> $authors
     * @return PaperStatus */
    private function web_save($authors, $expect_ok = true) {
        $ps = new PaperStatus($this->u_root);
        $ok = $ps->prepare_save_paper_web(new Qrequest("POST", ["has_authors" => "1"] + $authors), $this->paper());
        if ($ok) {
            $ok = $ps->execute_save();
        }
        xassert_eqq($ok, $expect_ok);
        return $ps;
    }

    function test_web_form_orcid() {
        // set, normalize
        $ps = $this->web_save([
            "authors:1:name" => "Josiah Carberry", "authors:1:email" => "carberry@_.com", "authors:1:orcid" => self::O1,
            "authors:2:name" => "Laurel Haak", "authors:2:email" => "haak@_.com", "authors:2:orcid" => " https://orcid.org/0000000151093700 ",
            "authors:3:name" => "New Author", "authors:3:email" => "new@_.com", "authors:3:orcid" => ""
        ]);
        xassert(!$ps->has_problem_at("authors"));
        xassert(!$ps->has_problem_at("authors:2:orcid"));
        xassert_eqq($this->orcids(), [self::O1, self::O2, ""]);

        // reorder with the inputs
        $this->web_save([
            "authors:1:name" => "Laurel Haak", "authors:1:email" => "haak@_.com", "authors:1:orcid" => self::O2,
            "authors:2:name" => "Josiah Carberry", "authors:2:email" => "carberry@_.com", "authors:2:orcid" => self::O1,
            "authors:3:name" => "New Author", "authors:3:email" => "new@_.com", "authors:3:orcid" => ""
        ]);
        xassert_eqq($this->orcids(), [self::O2, self::O1, ""]);

        // clear
        $this->web_save([
            "authors:1:name" => "Laurel Haak", "authors:1:email" => "haak@_.com", "authors:1:orcid" => "",
            "authors:2:name" => "Josiah Carberry", "authors:2:email" => "carberry@_.com", "authors:2:orcid" => self::O1,
            "authors:3:name" => "New Author", "authors:3:email" => "new@_.com", "authors:3:orcid" => ""
        ]);
        xassert_eqq($this->orcids(), ["", self::O1, ""]);

        // invalid iD: nothing saved
        $before = $this->paper()->authorInformation;
        $ps = $this->web_save([
            "authors:1:name" => "Laurel Haak", "authors:1:email" => "haak@_.com", "authors:1:orcid" => "0000-0001-5109-3701",
            "authors:2:name" => "Josiah Carberry", "authors:2:email" => "carberry@_.com", "authors:2:orcid" => self::O1
        ], false);
        xassert($ps->has_error_at("authors:1:orcid"));
        xassert_str_contains($ps->full_feedback_text(), "Invalid ORCID iD");
        xassert_eqq($this->paper()->authorInformation, $before);

        // invalid iD via JSON: nothing saved
        $ps = new PaperStatus($this->u_root);
        xassert(!$ps->save_paper_json((object) ["id" => $this->pid, "authors" => [
            (object) ["name" => "Laurel Haak", "email" => "haak@_.com", "orcid" => "not an orcid"]
        ]]));
        xassert($ps->has_error_at("authors:1:orcid"));
        xassert_eqq($this->paper()->authorInformation, $before);

        // duplicate iD: warning, saved
        $ps = $this->web_save([
            "authors:1:name" => "Laurel Haak", "authors:1:email" => "haak@_.com", "authors:1:orcid" => self::O1,
            "authors:2:name" => "Josiah Carberry", "authors:2:email" => "carberry@_.com", "authors:2:orcid" => self::O1
        ]);
        xassert(!$ps->has_error());
        xassert($ps->has_problem_at("authors:1:orcid"));
        xassert($ps->has_problem_at("authors:2:orcid"));
        xassert_str_contains($ps->full_feedback_text(), "same ORCID iD");
        xassert_eqq($this->orcids(), [self::O1, self::O1]);

        // ORCID iD without an author: warning, dropped
        $ps = $this->web_save([
            "authors:1:name" => "Laurel Haak", "authors:1:email" => "haak@_.com", "authors:1:orcid" => self::O2,
            "authors:2:orcid" => self::O1,
            "authors:3:name" => "Josiah Carberry", "authors:3:email" => "carberry@_.com", "authors:3:orcid" => self::O1
        ]);
        xassert($ps->has_problem_at("authors:2:orcid"));
        xassert_eqq($this->orcids(), [self::O2, self::O1]);
    }

    function test_profile_prefill() {
        $u = $this->conf->checked_user_by_email("estrin@usc.edu");
        $old_orcid = $u->prop("orcid");
        $u->set_prop("orcid", self::O3);
        $u->save_prop();
        $this->conf->invalidate_caches("users");

        // JSON without `orcid`: filled from profile
        $ps = new PaperStatus($this->u_root);
        xassert($ps->save_paper_json((object) ["id" => $this->pid, "authors" => [
            (object) ["name" => "Laurel Haak", "email" => "haak@_.com"],
            (object) ["name" => "Deborah Estrin", "email" => "estrin@usc.edu"]
        ]]));
        xassert_eqq($this->orcids(), [self::O2, self::O3]);

        // removing the saved iD sticks for this save
        $this->web_save([
            "authors:1:name" => "Laurel Haak", "authors:1:email" => "haak@_.com", "authors:1:orcid" => self::O2,
            "authors:2:name" => "Deborah Estrin", "authors:2:email" => "estrin@usc.edu", "authors:2:orcid" => ""
        ]);
        xassert_eqq($this->orcids(), [self::O2, ""]);

        // a later save with the field still empty refills from the profile
        $this->web_save([
            "authors:1:name" => "Laurel Haak", "authors:1:email" => "haak@_.com", "authors:1:orcid" => self::O2,
            "authors:2:name" => "Deborah Estrin", "authors:2:email" => "estrin@usc.edu", "authors:2:orcid" => ""
        ]);
        xassert_eqq($this->orcids(), [self::O2, self::O3]);

        // a different iD on the paper wins over the profile
        $this->web_save([
            "authors:1:name" => "Deborah Estrin", "authors:1:email" => "estrin@usc.edu", "authors:1:orcid" => self::O1
        ]);
        xassert_eqq($this->orcids(), [self::O1]);

        // no profile iD, no fill; never by name alone
        $this->web_save([
            "authors:1:name" => "Deborah Estrin", "authors:1:orcid" => "",
            "authors:2:name" => "Laurel Haak", "authors:2:email" => "haak@_.com", "authors:2:orcid" => ""
        ]);
        xassert_eqq($this->orcids(), ["", ""]);

        $u = $this->conf->checked_user_by_email("estrin@usc.edu");
        $u->set_prop("orcid", $old_orcid);
        $u->save_prop();
        $this->conf->invalidate_caches("users");
    }

    function test_profile_field_prefills_submission() {
        // an iD saved through the profile code path (UserStatus) is
        // normalized and then pre-fills that author on a submission
        $email = "orcidprofile@_.com";
        $us = new UserStatus($this->u_root);
        $u = $us->save_user((object) ["email" => $email, "given_name" => "Josiah", "family_name" => "Carberry", "orcid" => "https://orcid.org/0000000218250097"]);
        xassert(!!$u, $us->full_feedback_text());
        $this->conf->invalidate_caches("users");
        xassert_eqq($this->conf->checked_user_by_email($email)->orcid(), self::O1);

        // invalid profile iDs are rejected
        $us = new UserStatus($this->u_root);
        xassert(!$us->save_user((object) ["email" => $email, "orcid" => "0000-0002-1825-0098"]));
        xassert($us->has_error_at("orcid"));

        $ps = new PaperStatus($this->u_root);
        xassert($ps->save_paper_json((object) ["id" => $this->pid, "authors" => [
            (object) ["name" => "Josiah Carberry", "email" => $email]
        ]]));
        xassert_eqq($this->orcids(), [self::O1]);
    }

    function test_edit_form() {
        $this->web_save([
            "authors:1:name" => "Josiah Carberry", "authors:1:email" => "carberry@_.com", "authors:1:orcid" => self::O1
        ]);
        $qreq = (new Qrequest("GET", ["p" => $this->pid]))->set_user($this->u_root)->set_navigation(Navigation::get());
        $pt = new PaperTable($qreq, $this->paper());
        $ov = $this->paper()->option(PaperOption::AUTHORSID);
        ob_start();
        $ov->option->print_web_edit($pt, $ov, $ov);
        $t = ob_get_clean();
        xassert_str_contains($t, 'name="authors:1:orcid"');
        xassert_str_contains($t, 'value="' . self::O1 . '"');
        xassert_str_contains($t, 'name="authors:$:orcid"'); // row template
    }

    function test_export_json() {
        $pj = json_decode(json_encode((new PaperExport($this->u_root))->paper_json($this->paper())));
        xassert_eqq($pj->authors[0]->orcid ?? null, self::O1);
        $this->web_save([
            "authors:1:name" => "Josiah Carberry", "authors:1:email" => "carberry@_.com", "authors:1:orcid" => ""
        ]);
        $pj = json_decode(json_encode((new PaperExport($this->u_root))->paper_json($this->paper())));
        xassert(!isset($pj->authors[0]->orcid));
        xassert(!isset(Author::make_tabbed("A\tB\ta@b.c\t\t" . self::O1)->unparse_nea_json()["orcid"]));
    }

    function test_blind_reviewer_sees_no_orcid() {
        // paper 1 is submitted and blind; PC members cannot see its authors
        $prow = $this->conf->checked_paper_by_id(1, $this->u_root);
        $old = $prow->authorInformation;
        $aus = $prow->author_list();
        $aus[0]->orcid = self::O1;
        $this->conf->qe("update Paper set authorInformation=? where paperId=1",
            join("\n", array_map(function ($au) { return $au->unparse_tabbed(); }, $aus)));

        $u_pc = $this->conf->checked_user_by_email("mgbaker@cs.stanford.edu");
        $prow = $this->conf->checked_paper_by_id(1, $u_pc);
        xassert($u_pc->can_view_paper($prow));
        xassert(!$u_pc->can_view_authors($prow));
        $pj = (new PaperExport($u_pc))->paper_json($prow);
        xassert(!isset($pj->authors));
        xassert(strpos(json_encode($pj), self::O1) === false);

        $prow = $this->conf->checked_paper_by_id(1, $this->u_root);
        $pj = json_decode(json_encode((new PaperExport($this->u_root))->paper_json($prow)));
        xassert_eqq($pj->authors[0]->orcid ?? null, self::O1);

        $this->conf->qe("update Paper set authorInformation=? where paperId=1", $old);
    }

    // ---- Phase 3: name check against the public ORCID registry (faked)

    /** @var int */
    private $nfetch = 0;

    /** @param string $given
     * @param string $family
     * @param ?string $credit
     * @param list<string> $others
     * @return string */
    static private function record_json($given, $family, $credit = null, $others = []) {
        $on = [];
        foreach ($others as $o) {
            $on[] = ["content" => $o, "visibility" => "public"];
        }
        return json_encode([
            "name" => [
                "given-names" => $given === null ? null : ["value" => $given],
                "family-name" => $family === null ? null : ["value" => $family],
                "credit-name" => $credit === null ? null : ["value" => $credit]
            ],
            "other-names" => ["other-name" => $on]
        ]);
    }

    private function install_fake_registry() {
        $this->nfetch = 0;
        OrcidLookup::reset();
        OrcidLookup::$fetcher = function ($orcid) {
            ++$this->nfetch;
            if ($orcid === self::O1) {
                return [200, self::record_json("Josiah", "Carberry", null, ["Josiah Stinkney Carberry", "J. S. Carberry"])];
            } else if ($orcid === self::O2) {
                return [200, self::record_json("Laurel", "Haak", "Laurel L Haak")];
            } else if ($orcid === self::O3) {
                return [404, '{"error":"not found"}'];
            } else {
                return [0, null];
            }
        };
    }

    private function remove_fake_registry() {
        OrcidLookup::$fetcher = null;
        OrcidLookup::reset();
    }

    function test_lookup_parse() {
        $r = OrcidLookup::parse(self::O1, 200, self::record_json("Josiah", "Carberry", null, ["Josiah Stinkney Carberry"]));
        xassert_eqq($r->status, OrcidRecord::FOUND);
        xassert_eqq($r->display_name(), "Josiah Carberry");
        xassert_eqq($r->others, ["Josiah Stinkney Carberry"]);
        $r = OrcidLookup::parse(self::O2, 200, self::record_json("Laurel", "Haak", "Laurel L Haak"));
        xassert_eqq($r->display_name(), "Laurel L Haak");
        xassert_eqq(OrcidLookup::parse(self::O1, 404, "")->status, OrcidRecord::NOT_FOUND);
        xassert_eqq(OrcidLookup::parse(self::O1, 500, "{}")->status, OrcidRecord::UNAVAILABLE);
        xassert_eqq(OrcidLookup::parse(self::O1, 0, null)->status, OrcidRecord::UNAVAILABLE);
        xassert_eqq(OrcidLookup::parse(self::O1, 200, "not json")->status, OrcidRecord::UNAVAILABLE);
        xassert_eqq(OrcidLookup::parse(self::O1, 200, '{"name":null,"other-names":{"other-name":[]}}')->status, OrcidRecord::NAME_HIDDEN);
        // the real response format (trimmed), as fetched from pub.orcid.org
        $r = OrcidLookup::parse(self::O1, 200, '{"last-modified-date":{"value":1504850007188},"name":{"given-names":{"value":"Josiah"},"family-name":{"value":"Carberry"},"credit-name":null,"source":null,"visibility":"public","path":"0000-0002-1825-0097"},"other-names":{"other-name":[{"content":"Josiah Stinkney Carberry","visibility":"public","display-index":3}]}}');
        xassert_eqq([$r->given, $r->family, $r->credit, $r->others], ["Josiah", "Carberry", "", ["Josiah Stinkney Carberry"]]);
    }

    function test_name_matches() {
        $r = OrcidLookup::parse(self::O1, 200, self::record_json("Josiah", "Carberry", null, ["Josiah Stinkney Carberry"]));
        xassert(OrcidLookup::name_matches($r, "Josiah", "Carberry"));
        xassert(OrcidLookup::name_matches($r, "J.", "Carberry"));
        xassert(OrcidLookup::name_matches($r, "josiah s.", "CARBERRY"));
        xassert(OrcidLookup::name_matches($r, "", "Carberry"));
        xassert(OrcidLookup::name_matches($r, "Carberry", "Josiah")); // family name first
        xassert(OrcidLookup::name_matches($r, "Josiah", "Stinkney Carberry")); // other name
        xassert(!OrcidLookup::name_matches($r, "Laurel", "Haak"));
        xassert(!OrcidLookup::name_matches($r, "Laurel", "Carberry"));
        xassert(!OrcidLookup::name_matches($r, "Josiah", "Carbery"));
        $r = OrcidLookup::parse(self::O1, 200, self::record_json("José", "Müller-Lüdenscheidt"));
        xassert(OrcidLookup::name_matches($r, "Jose", "Muller-Ludenscheidt"));
        xassert(OrcidLookup::name_matches($r, "José", "Müller Lüdenscheidt"));
        $r = OrcidLookup::parse(self::O1, 200, self::record_json("Xiaoyun", "Wang"));
        xassert(OrcidLookup::name_matches($r, "Xiaoyun", "Wang"));
        xassert(OrcidLookup::name_matches($r, "Wang", "Xiaoyun"));
        $r = OrcidLookup::parse(self::O1, 200, self::record_json(null, "Plato"));
        xassert(OrcidLookup::name_matches($r, "", "Plato"));
        xassert(OrcidLookup::name_matches($r, "Aristocles", "Plato")); // no given name to compare
        // unknown records never mismatch
        xassert(OrcidLookup::name_matches(new OrcidRecord(self::O1, OrcidRecord::UNAVAILABLE), "A", "B"));
        xassert(OrcidLookup::name_matches(new OrcidRecord(self::O1, OrcidRecord::NAME_HIDDEN), "A", "B"));
    }

    function test_lookup_disabled_without_fetcher_in_cli() {
        OrcidLookup::$fetcher = null;
        OrcidLookup::reset();
        xassert(!OrcidLookup::enabled());
        xassert_eqq(OrcidLookup::lookup(self::O1)->status, OrcidRecord::UNAVAILABLE);
        xassert_eqq(OrcidLookup::mismatch_message(self::O1, "Laurel", "Haak"), null);
    }

    function test_submission_name_check() {
        $this->install_fake_registry();

        // mismatch: warning naming the record’s owner, but saved
        $ps = $this->web_save([
            "authors:1:name" => "Laurel Haak", "authors:1:email" => "haak@_.com", "authors:1:orcid" => self::O1,
            "authors:2:name" => "Josiah Carberry", "authors:2:email" => "carberry@_.com", "authors:2:orcid" => self::O2
        ]);
        xassert(!$ps->has_error());
        xassert($ps->has_problem_at("authors:1:orcid"));
        xassert($ps->has_problem_at("authors:2:orcid"));
        xassert_str_contains($ps->full_feedback_text(), "ORCID iD " . self::O1 . " belongs to “Josiah Carberry”");
        xassert_str_contains($ps->full_feedback_text(), "belongs to “Laurel L Haak”");
        xassert_eqq($this->orcids(), [self::O1, self::O2]);
        xassert_eqq($this->nfetch, 2);

        // unchanged iDs are not looked up again
        OrcidLookup::reset();
        $ps = $this->web_save([
            "authors:1:name" => "Laurel Haak", "authors:1:email" => "haak@_.com", "authors:1:orcid" => self::O1,
            "authors:2:name" => "Josiah Carberry", "authors:2:email" => "carberry@_.com", "authors:2:orcid" => self::O2
        ]);
        xassert(!$ps->has_problem_at("authors:1:orcid"));
        xassert_eqq($this->nfetch, 2);

        // matching names: no warning
        $ps = $this->web_save([
            "authors:1:name" => "Laurel Haak", "authors:1:email" => "haak@_.com", "authors:1:orcid" => self::O2,
            "authors:2:name" => "J. Carberry", "authors:2:email" => "carberry@_.com", "authors:2:orcid" => self::O1
        ]);
        xassert(!$ps->has_problem_at("authors:1:orcid"));
        xassert(!$ps->has_problem_at("authors:2:orcid"));

        // no such record: warning; registry down: no warning
        $ps = $this->web_save([
            "authors:1:name" => "Laurel Haak", "authors:1:email" => "haak@_.com", "authors:1:orcid" => self::O3,
            "authors:2:name" => "Somebody Else", "authors:2:email" => "else@_.com", "authors:2:orcid" => "0000-0003-1415-9269"
        ]);
        xassert(!$ps->has_error());
        xassert_str_contains($ps->full_feedback_text(), "no ORCID record for " . self::O3);
        xassert(!$ps->has_problem_at("authors:2:orcid"));
        xassert_eqq($this->orcids(), [self::O3, "0000-0003-1415-9269"]);

        // JSON saves are checked too
        $ps = new PaperStatus($this->u_root);
        xassert($ps->save_paper_json((object) ["id" => $this->pid, "authors" => [
            (object) ["name" => "Laurel Haak", "email" => "haak@_.com", "orcid" => self::O1]
        ]]));
        xassert($ps->has_problem_at("authors:1:orcid"));

        $this->remove_fake_registry();
    }

    function test_profile_name_check() {
        $this->install_fake_registry();
        $email = "orcidprofile@_.com"; // Josiah Carberry
        $us = new UserStatus($this->u_root);
        xassert(!!$us->save_user((object) ["email" => $email, "orcid" => self::O2]));
        xassert($us->has_problem_at("orcid"));
        xassert_str_contains($us->full_feedback_text(), "belongs to “Laurel L Haak”");
        $this->conf->invalidate_caches("users");
        xassert_eqq($this->conf->checked_user_by_email($email)->orcid(), self::O2);

        $us = new UserStatus($this->u_root);
        xassert(!!$us->save_user((object) ["email" => $email, "orcid" => self::O1]));
        xassert(!$us->has_problem_at("orcid"));

        // unchanged: not looked up
        $n = $this->nfetch;
        OrcidLookup::reset();
        $this->conf->invalidate_caches("users");
        $us = new UserStatus($this->u_root);
        xassert(!!$us->save_user((object) ["email" => $email, "orcid" => self::O1]));
        xassert_eqq($this->nfetch, $n);
        $this->remove_fake_registry();
    }

    // ---- Phase 5: IACR outputs

    private function set_test_authors() {
        $ps = new PaperStatus($this->u_root);
        xassert($ps->save_paper_json((object) ["id" => $this->pid, "authors" => [
            (object) ["name" => "Josiah Carberry", "email" => "carberry@_.com", "affiliation" => "Brown", "orcid" => self::O1],
            (object) ["name" => "Laurel Haak", "email" => "haak@_.com", "orcid" => ""]
        ]]));
        xassert_eqq($this->orcids(), [self::O1, ""]);
    }

    function test_accepted_json_orcids() {
        $this->set_test_authors();
        $old_type = $this->conf->opt("iacrType");
        $this->conf->qe("update Paper set outcome=1 where paperId=?", $this->pid);
        try {
            $this->conf->set_opt("iacrType", "crypto");
            $page = new IACR_TestAcceptedJson_Page($this->conf);
            $page->source = new IACR_TestFinalPapers($this->conf, []);
            $data = $page->papers_json();
            $mine = null;
            foreach ($data["acceptedPapers"] as $pj) {
                if ($pj["paperId"] === (string) $this->pid) {
                    $mine = $pj;
                } else {
                    xassert(!isset($pj["orcids"])); // papers without iDs unchanged
                }
            }
            xassert_eqq($mine["authors"], ["Josiah Carberry", "Laurel Haak"]);
            xassert_eqq($mine["orcids"], [self::O1, ""]);
            xassert_eqq($mine["affiliations"], ["Brown", ""]);
        } finally {
            $this->conf->qe("update Paper set outcome=0 where paperId=?", $this->pid);
            $this->conf->set_opt("iacrType", $old_type);
        }
    }

    function test_copyright_authors_without_orcids() {
        $this->set_test_authors();
        $prow = $this->paper();
        xassert_str_contains($prow->authorInformation, self::O1);
        $s = IacrCopyright_Page::authors_without_orcids($prow);
        xassert_eqq($s, "Josiah\tCarberry\tcarberry@_.com\tBrown\nLaurel\tHaak\thaak@_.com\t");
        // papers without iDs are byte-for-byte unchanged
        $prow = $this->conf->checked_paper_by_id(1, $this->u_root);
        xassert_eqq(IacrCopyright_Page::authors_without_orcids($prow), $prow->authorInformation);
    }

    function test_get_authors_csv() {
        $this->set_test_authors();
        // an account iD fills in for an author with none on the paper,
        // but the paper’s iD wins when both exist
        foreach (["carberry@_.com" => self::O2, "haak@_.com" => self::O3] as $email => $o) {
            $us = new UserStatus($this->u_root);
            xassert(!!$us->save_user((object) ["email" => $email, "orcid" => $o]));
        }
        $this->conf->invalidate_caches("users");
        $qreq = TestQreq::get(["p" => (string) $this->pid]);
        $ssel = SearchSelection::make_papers($qreq, $this->u_root);
        $la = ListAction::lookup("get/authors", $this->u_root, $qreq, $ssel);
        xassert($la instanceof ListAction);
        $csvg = $la->run($this->u_root, $qreq, $ssel);
        $rows = new CsvParser($csvg->unparse());
        $rows->set_header($rows->next_list());
        $got = [];
        while (($row = $rows->next_row())) {
            $got[$row["email"]] = $row["orcid"];
        }
        xassert_eqq($got["carberry@_.com"], self::O1);
        xassert_eqq($got["haak@_.com"], self::O3 . "?"); // typed profile iD: unconfirmed
    }

    // ---- Phase 6: upstream's (hidden) ORCID requirement

    function test_require_orcid_accepts_paper_orcid() {
        $this->conf->set_opt("requireOrcid", 1);
        try {
            $ps = $this->web_save([
                "authors:1:name" => "Josiah Carberry", "authors:1:email" => "noorcid1@_.com", "authors:1:orcid" => "",
                "authors:2:name" => "Laurel Haak", "authors:2:email" => "noorcid2@_.com", "authors:2:orcid" => self::O2
            ]);
            $t = $ps->full_feedback_text();
            xassert_str_contains($t, "Please ask noorcid1@_.com to sign in");
            xassert(!str_contains($t, "noorcid2@_.com"));

            $ps = $this->web_save([
                "authors:1:name" => "Josiah Carberry", "authors:1:email" => "noorcid1@_.com", "authors:1:orcid" => self::O1,
                "authors:2:name" => "Laurel Haak", "authors:2:email" => "noorcid2@_.com", "authors:2:orcid" => self::O2
            ]);
            xassert(!str_contains($ps->full_feedback_text(), "requires that all authors provide ORCID iDs"));
        } finally {
            $this->conf->set_opt("requireOrcid", null);
        }
    }

    // ---- Unconfirmed profile iDs and computed confirmation status

    /** @param string $email
     * @param ?string $orcid */
    private function set_account_orcid($email, $orcid) {
        $u = $this->conf->fresh_user_by_email($email);
        if (!$u) {
            $us = new UserStatus($this->u_root);
            xassert(!!$us->save_user((object) ["email" => $email]));
            $u = $this->conf->fresh_user_by_email($email);
        }
        $u->set_prop("orcid", $orcid);
        $u->save_prop();
        $this->conf->invalidate_caches("users");
    }

    function test_profile_orcids_unconfirmed() {
        $email = "orcidconfirm@_.com";
        // newly entered: unconfirmed
        $us = new UserStatus($this->u_root);
        xassert(!!$us->save_user((object) ["email" => $email, "orcid" => "https://orcid.org/" . self::O1]));
        $u = $this->conf->fresh_user_by_email($email);
        xassert_eqq($u->decorated_orcid(), self::O1 . "?");
        xassert_eqq($u->orcid(), self::O1);
        xassert_eqq($u->confirmed_orcid(), "");

        // a decorated value is accepted (e.g., re-uploading a download)
        $us = new UserStatus($this->u_root);
        xassert(!!$us->save_user((object) ["email" => "orcidconfirm2@_.com", "orcid" => self::O2 . "?"]));
        xassert_eqq($this->conf->fresh_user_by_email("orcidconfirm2@_.com")->decorated_orcid(), self::O2 . "?");
        $us = new UserStatus($this->u_root);
        xassert(!$us->save_user((object) ["email" => "orcidconfirm2@_.com", "orcid" => "0000-0002-1825-0098?"]));

        // a confirmed iD stays confirmed when re-entered, with or without “?”
        $this->set_account_orcid($email, self::O1);
        foreach ([self::O1, self::O1 . "?", "https://orcid.org/" . self::O1] as $v) {
            $us = new UserStatus($this->u_root);
            xassert(!!$us->save_user((object) ["email" => $email, "orcid" => $v]));
            xassert_eqq($this->conf->fresh_user_by_email($email)->confirmed_orcid(), self::O1);
        }
        // a different iD is unconfirmed; clearing clears
        $us = new UserStatus($this->u_root);
        xassert(!!$us->save_user((object) ["email" => $email, "orcid" => self::O2]));
        xassert_eqq($this->conf->fresh_user_by_email($email)->decorated_orcid(), self::O2 . "?");
        $us = new UserStatus($this->u_root);
        xassert(!!$us->save_user((object) ["email" => $email, "orcid" => ""]));
        xassert_eqq($this->conf->fresh_user_by_email($email)->decorated_orcid(), "");
    }

    function test_orcid_status() {
        $this->set_account_orcid("carberry@_.com", self::O1);        // confirmed, same iD
        $this->set_account_orcid("haak@_.com", self::O2 . "?");      // unconfirmed
        $this->set_account_orcid("estrin@usc.edu", self::O3);        // confirmed, other iD
        $aus = PaperInfo::parse_author_list(
            "Josiah\tCarberry\tcarberry@_.com\t\t" . self::O1 . "\n"
            . "Laurel\tHaak\thaak@_.com\t\t" . self::O2 . "\n"
            . "Deborah\tEstrin\testrin@usc.edu\t\t" . self::O1 . "\n"
            . "No\tEmail\t\t\t" . self::O1 . "\n"
            . "No\tAccount\tnoaccount@_.com\t\t" . self::O1 . "\n"
            . "No\tOrcid\tcarberry@_.com\t");
        Author::prefetch_orcid_status($this->conf, $aus);
        xassert_eqq(array_map(function ($au) { return $au->orcid_status($this->conf); }, $aus),
            [Author::ORCID_CONFIRMED, Author::ORCID_UNCONFIRMED, Author::ORCID_UNCONFIRMED,
             Author::ORCID_UNCONFIRMED, Author::ORCID_UNCONFIRMED, Author::ORCID_NONE]);
        // email case does not matter
        xassert_eqq(Author::make_tabbed("J\tC\tCARBERRY@_.com\t\t" . self::O1)->orcid_status($this->conf), Author::ORCID_CONFIRMED);

        // computed, not stored: confirming the account confirms the paper iD
        $this->web_save([
            "authors:1:name" => "Josiah Carberry", "authors:1:email" => "carberry@_.com", "authors:1:orcid" => self::O1,
            "authors:2:name" => "Laurel Haak", "authors:2:email" => "haak@_.com", "authors:2:orcid" => self::O2
        ]);
        $pj = json_decode(json_encode((new PaperExport($this->u_root))->paper_json($this->paper())));
        xassert_eqq([$pj->authors[0]->orcid_confirmed, $pj->authors[1]->orcid_confirmed], [true, false]);
        $before = $this->paper()->authorInformation;
        xassert(!str_contains($before, "?"));
        $this->set_account_orcid("haak@_.com", self::O2);
        $pj = json_decode(json_encode((new PaperExport($this->u_root))->paper_json($this->paper())));
        xassert_eqq([$pj->authors[0]->orcid_confirmed, $pj->authors[1]->orcid_confirmed], [true, true]);
        xassert_eqq($this->paper()->authorInformation, $before);

        // paper page marks confirmed iDs
        $qreq = (new Qrequest("GET", ["p" => $this->pid]))->set_user($this->u_root)->set_navigation(Navigation::get());
        $this->set_account_orcid("haak@_.com", self::O2 . "?");
        $pt = new PaperTable($qreq, $this->paper());
        $m = new ReflectionMethod("PaperTable", "authorData");
        $m->setAccessible(true);
        $h = $m->invoke($pt, $this->paper()->author_list(), "col", null);
        xassert_str_contains($h, "ORCID " . self::O1 . " ✓</a>");
        xassert_str_contains($h, "ORCID " . self::O2 . "</a>");
        xassert_str_contains($h, "not confirmed");

        // author CSV
        $qreq = TestQreq::get(["p" => (string) $this->pid]);
        $ssel = SearchSelection::make_papers($qreq, $this->u_root);
        $csvg = ListAction::lookup("get/authors", $this->u_root, $qreq, $ssel)->run($this->u_root, $qreq, $ssel);
        $rows = new CsvParser($csvg->unparse());
        $rows->set_header($rows->next_list());
        $got = [];
        while (($row = $rows->next_row())) {
            $got[$row["email"]] = [$row["orcid"], $row["orcid_confirmed"]];
        }
        xassert_eqq($got["carberry@_.com"], [self::O1, "yes"]);
        xassert_eqq($got["haak@_.com"], [self::O2, "no"]);

        $this->set_account_orcid("carberry@_.com", null);
        $this->set_account_orcid("haak@_.com", null);
        $this->set_account_orcid("estrin@usc.edu", null);
    }
}
