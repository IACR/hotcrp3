<?php
// t_iacr_pages.php -- HotCRP tests for IACR's fork: the IACR chair pages
// (/iacr_*, src/pages/p_iacr_*.php). Not part of upstream HotCRP.

require_once(SiteLoader::resolve("src/pages/iacr/finalpapers.php"));
require_once(SiteLoader::resolve("src/pages/iacr/frontmatter.php"));
require_once(SiteLoader::resolve("src/pages/iacr/lncswork.php"));

/** Final-version data from a fixed list instead of iacr.org. */
class IACR_TestFinalPapers extends IACR_FinalPapers {
    /** @var ?list<array<string,mixed>> */
    public $remote;
    /** @var int */
    public $fetches = 0;

    function __construct(Conf $conf, $remote) {
        parent::__construct($conf);
        $this->remote = $remote;
    }

    protected function fetch_remote() {
        ++$this->fetches;
        return $this->remote;
    }

    function api_url($action) {
        return "https://www.iacr.org/submit/api/?action={$action}&auth=X";
    }
}

/** For IACR pages that read final versions: use `$source` instead of
 * iacr.org. */
trait IACR_TestSource {
    /** @var IACR_TestFinalPapers */
    public $source;

    protected function final_papers_source() {
        return $this->source;
    }
}

/** For IACR pages that keep LNCS editor work: save it in `$dir`. */
trait IACR_TestWork {
    /** @var string */
    public $dir;

    protected function work() {
        return new IACR_LncsWork($this->user, $this->dir);
    }
}

class IACR_TestLncs_Page extends IACR_Lncs_Page {
    use IACR_TestSource;
}
class IACR_TestFinalJson_Page extends IACR_FinalJson_Page {
    use IACR_TestSource;
}
class IACR_TestFrontmatter_Page extends IACR_Frontmatter_Page {
    use IACR_TestSource;
}
class IACR_TestLncsEditor_Page extends IACR_LncsEditor_Page {
    use IACR_TestSource;
}
class IACR_TestLncsData_Page extends IACR_LncsData_Page {
    use IACR_TestSource;
    use IACR_TestWork;
}
class IACR_TestLncsFrontmatter_Page extends IACR_LncsFrontmatter_Page {
    use IACR_TestWork;
}

class IACR_TestAcceptedJson_Page extends IACR_AcceptedJson_Page {
    /** @var IACR_TestFinalPapers */
    public $source;

    protected function final_papers_source() {
        return $this->source;
    }
}

class IACR_TestFinal_Page extends IACR_Final_Page {
    protected function download_url() {
        return "https://www.iacr.org/submit/api/?action=download&auth=X";
    }
}

/** Copyright forms from a fixed list instead of the copyright database. */
class IACR_TestCopyrights_Page extends IACR_Copyrights_Page {
    /** @var ?list<array{paperId:mixed,title:string}> */
    public $rows;

    protected function signed_copyrights() {
        return $this->rows;
    }
}

class IACR_Pages_Tester {
    /** @var Conf
     * @readonly */
    public $conf;
    /** @var Contact */
    private $u_chair;
    /** @var Contact */
    private $u_pc;

    function __construct(Conf $conf) {
        $this->conf = $conf;
        $this->u_chair = $this->signed_in_user("chair@_.com");
        $this->u_pc = $this->signed_in_user("estrin@usc.edu");
    }

    /** @param string $email
     * @return Contact */
    private function signed_in_user($email) {
        return $this->conf->fresh_user_by_email($email)->activate(TestQreq::get(), false);
    }

    /** @return list<object> */
    private function iacr_page_entries() {
        $pages = json_decode(file_get_contents(SiteLoader::resolve("etc/pages.json")));
        return array_values(array_filter($pages, function ($j) {
            return is_object($j) && str_starts_with($j->name ?? "", "iacr_");
        }));
    }

    /** Run IACR page `$name` as `$user`.
     * @param string $name
     * @return array{?int,string} HTTP status if the page stopped early, and output */
    private function run_page(Contact $user, $name) {
        $gj = $this->conf->page_components($user, TestQreq::get())->get($name);
        return $this->run_print($user, $name, function ($user, $qreq) use ($gj) {
            call_user_func($gj->print_function, $user, $qreq);
        });
    }

    /** Call `$f($user, $qreq)` for a request for page `$name`.
     * @param string $name
     * @param callable(Contact,Qrequest):void $f
     * @return array{?int,string} HTTP status if the page stopped early, and output */
    private function run_print(Contact $user, $name, $f) {
        $qreq = TestQreq::get_page($name)->set_user($user);
        Qrequest::set_main_request($qreq);
        $status = null;
        $old_test_mode = Navigation::$test_mode;
        Navigation::$test_mode = 1;
        ob_start();
        try {
            $f($user, $qreq);
        } catch (PageCompletion $pc) {
            $status = Navigation::http_response_code();
        } finally {
            Navigation::$test_mode = $old_test_mode;
            $out = ob_get_clean();
        }
        return [$status, $out];
    }

    function test_iacr_pages_are_registered() {
        $entries = $this->iacr_page_entries();
        xassert(count($entries) > 0);
        foreach ($entries as $j) {
            // IACR pages follow IACR_<Name>_Page in src/pages/p_iacr_<name>.php
            xassert_eqq(preg_match('/\AIACR_[A-Za-z]+_Page::go\z/', $j->print_function), 1);
            xassert(is_callable($j->print_function));
            xassert(!!$this->conf->page_components($this->u_chair, TestQreq::get())->get($j->name));
        }
    }

    function test_iacr_pages_are_chair_only() {
        foreach ($this->iacr_page_entries() as $j) {
            list($status, $out) = $this->run_page($this->u_pc, $j->name);
            xassert_eqq($status, 403);
            xassert(!str_contains($out, "leftnav"));
        }
    }

    function test_home() {
        $old_type = $this->conf->opt("iacrType");
        $this->conf->set_opt("iacrType", "crypto");
        list($status, $out) = $this->run_page($this->u_chair, "iacr_home");
        xassert_eqq($status, null);
        xassert_str_contains($out, "IACR extensions to HotCRP");
        xassert_str_contains($out, 'id="menuhome" class="navitem active" href="' . $this->conf->hoturl("iacr_home") . '"');
        xassert_str_contains($out, 'id="menulncs" class="navitem" href="' . $this->conf->hoturl("iacr_lncs") . '"');
        xassert_str_contains($out, 'id="menufinal" class="navitem" href="' . $this->conf->hoturl("iacr_final") . '"');
        xassert_str_contains($out, 'id="menucopyright" class="navitem" href="' . $this->conf->hoturl("iacr_copyrights") . '"');
        xassert_str_contains($out, 'href="' . $this->conf->hoturl("iacr_accepted") . '"');
        xassert_str_contains($out, 'id="menulncs"');
        xassert_str_contains($out, 'id="menuprogram"');
        xassert_str_contains($out, 'href="' . $this->conf->hoturl("settings", ["group" => "decisions"]) . '"');
        xassert_str_ends_with($out, "</html>\n");
        // dark mode: iacr-pages.css loads after Bootstrap, which it overrides
        $this->check_stylesheet_order($out);

        // CiC has no LNCS volume or program
        $this->conf->set_opt("iacrType", "cic");
        list($status, $out) = $this->run_page($this->u_chair, "iacr_home");
        xassert(!str_contains($out, 'id="menulncs"'));
        xassert(!str_contains($out, 'id="menuprogram"'));
        xassert_str_contains($out, 'id="menucopyright"');
        $this->conf->set_opt("iacrType", $old_type);
    }

    function test_help_topic_links_home() {
        foreach ([[$this->u_chair, true], [$this->u_pc, false]] as [$user, $link]) {
            $hth = new HelpRenderer(new ComponentSet($user, ["etc/helptopics.json"]), $user);
            ob_start();
            IACR_HelpTopic::print($hth);
            $out = ob_get_clean();
            xassert_eqq(str_contains($out, 'href="' . $this->conf->hoturl("iacr_home") . '">Located here'), $link);
        }
    }

    /** Make papers 1 and 2 the only accepted papers, with topic "Lattices"
     * on paper 1, while running `$f`. */
    private function with_accepted_papers(callable $f) {
        $outcomes = $this->conf->fetch_rows("select paperId, outcome from Paper");
        $this->conf->qe("update Paper set outcome=0");
        $this->conf->qe("update Paper set outcome=1 where paperId in (1,2)");
        $this->conf->qe("insert into TopicArea set topicName='IACR test topic'");
        $tid = $this->conf->fetch_ivalue("select topicId from TopicArea where topicName='IACR test topic'");
        $this->conf->qe("insert into PaperTopic set paperId=1, topicId=?", $tid);
        try {
            $f();
        } finally {
            $this->conf->qe("delete from PaperTopic where topicId=?", $tid);
            $this->conf->qe("delete from TopicArea where topicId=?", $tid);
            foreach ($outcomes as [$pid, $outcome]) {
                $this->conf->qe("update Paper set outcome=? where paperId=?", $outcome, $pid);
            }
        }
    }

    /** @param ?list<array<string,mixed>> $remote
     * @return IACR_TestAcceptedJson_Page */
    private function accepted_json_page($remote) {
        $page = new IACR_TestAcceptedJson_Page($this->conf);
        $page->source = new IACR_TestFinalPapers($this->conf, $remote);
        return $page;
    }

    function test_accepted_json() {
        $old_type = $this->conf->opt("iacrType");
        $remote = [["paperId" => "1", "title" => "Final title", "abstract" => "Final abstract",
                    "pages" => 12, "pubkey" => "K1"]];
        $this->with_accepted_papers(function () use ($remote) {
            $p1 = $this->conf->checked_paper_by_id(1);
            $p2 = $this->conf->checked_paper_by_id(2);

            // a conference merges final-version data from iacr.org
            $this->conf->set_opt("iacrType", "crypto");
            $page = $this->accepted_json_page($remote);
            $data = $page->papers_json();
            xassert_eqq($data["_source"], "IACR/hotcrp v2");
            xassert_eqq(array_column($data["acceptedPapers"], "paperId"), ["1", "2"]);
            list($j1, $j2) = $data["acceptedPapers"];
            xassert_eqq(array_keys($j1), ["paperId", "title", "abstract", "authors", "affiliations", "pubkey", "keywords", "pages"]);
            xassert_eqq($j1["title"], "Final title");
            xassert_eqq($j1["abstract"], "Final abstract");
            xassert_eqq($j1["pages"], 12);
            xassert_eqq($j1["pubkey"], "K1");
            xassert_eqq($j1["keywords"], "IACR test topic");
            xassert_eqq(count($j1["authors"]), count($p1->author_list()));
            xassert_eqq($j1["authors"][0], $p1->author_list()[0]->firstName . " " . $p1->author_list()[0]->lastName);
            xassert_eqq(count($j1["affiliations"]), count(array_unique(array_map(function ($au) {
                return $au->affiliation;
            }, $p1->author_list()))));
            // no final version: HotCRP data
            xassert_eqq(array_keys($j2), ["paperId", "title", "abstract", "authors", "affiliations"]);
            xassert_eqq($j2["title"], $p2->title);

            // journals prefix IDs with the instance name, but still match
            // final versions by plain paper ID
            $db = $this->conf->opt("dbName");
            $this->conf->set_opt("iacrType", "tosc");
            $page = $this->accepted_json_page($remote);
            $data = $page->papers_json();
            xassert_eqq(array_column($data["acceptedPapers"], "paperId"), ["{$db}_1", "{$db}_2"]);
            xassert_eqq($data["acceptedPapers"][0]["title"], "Final title");
            xassert_eqq($data["acceptedPapers"][0]["keywords"], "IACR test topic");
            xassert_eqq($data["acceptedPapers"][1]["title"], $p2->title);
            xassert_eqq($page->source->fetches, 1);

            // CiC prefixes IDs too, but iacr.org has no CiC final versions
            $this->conf->set_opt("iacrType", "cic");
            $page = $this->accepted_json_page($remote);
            $data = $page->papers_json();
            xassert_eqq(array_column($data["acceptedPapers"], "paperId"), ["{$db}_1", "{$db}_2"]);
            xassert_eqq($data["acceptedPapers"][0]["title"], $p1->title);
            xassert_eqq($page->source->fetches, 0);

            // iacr.org unreachable: an error, not HotCRP-only titles
            $this->conf->set_opt("iacrType", "crypto");
            $page = $this->accepted_json_page(null);
            xassert_eqq($page->papers_json(), null);
            $old_test_mode = Navigation::$test_mode;
            Navigation::$test_mode = 1;
            $old_status = Navigation::http_response_code();
            ob_start();
            $page->print_json();
            $out = ob_get_clean();
            xassert_eqq(Navigation::http_response_code($old_status), 502);
            Navigation::$test_mode = $old_test_mode;
            xassert_eqq(json_decode($out, true), ["error" => "Unable to fetch data from www.iacr.org"]);
        });
        $this->conf->set_opt("iacrType", $old_type);
    }

    function test_parse_remote() {
        xassert_eqq(IACR_FinalPapers::parse_remote('[{"paperId":"1","title":"T"}]'), [["paperId" => "1", "title" => "T"]]);
        xassert_eqq(IACR_FinalPapers::parse_remote("[]"), []);
        xassert_eqq(IACR_FinalPapers::parse_remote('{"error":"bad auth"}'), null);
        xassert_eqq(IACR_FinalPapers::parse_remote('["x"]'), null);
        xassert_eqq(IACR_FinalPapers::parse_remote("<html>"), null);
        xassert_eqq(IACR_FinalPapers::parse_remote(false), null);
    }

    function test_accepted_and_program() {
        $old_type = $this->conf->opt("iacrType");
        $old_name = $this->conf->opt("longName");
        $json_url = $this->conf->hoturl("iacr_accepted_json");

        list($status, $out) = $this->run_page($this->u_chair, "iacr_accepted");
        xassert_eqq($status, null);
        xassert_str_contains($out, 'id="menuaccepted" class="navitem active"');
        xassert_str_contains($out, 'href="' . htmlspecialchars($json_url) . '" download="papers.json"');

        $this->conf->set_opt("iacrType", "tosc");
        $this->conf->set_opt("longName", "Crypto & <Friends>");
        list($status, $out) = $this->run_page($this->u_chair, "iacr_program");
        xassert_eqq($status, null);
        xassert_str_contains($out, 'id="menuprogram" class="navitem active"');
        xassert_str_contains($out, '$.getJSON(' . json_encode($json_url) . ',');
        xassert_str_contains($out, 'action="https://iacr.org/tools/program/receiveFromHotCRP.php"');
        xassert_str_contains($out, 'name="name" value="Crypto &amp; &lt;Friends&gt;"');
        xassert_str_contains($out, "multiple volumes");
        $this->conf->set_opt("iacrType", "crypto");
        list($status, $out) = $this->run_page($this->u_chair, "iacr_program");
        xassert(!str_contains($out, "multiple volumes"));

        $this->conf->set_opt("iacrType", $old_type);
        $this->conf->set_opt("longName", $old_name);
    }

    function test_final() {
        $search_url = htmlspecialchars($this->conf->hoturl("search", ["q" => "", "t" => "acc"]) . "#view");
        list($status, $out) = $this->run_print($this->u_chair, "iacr_final", function ($user, $qreq) {
            (new IACR_TestFinal_Page($user->conf))->print_page($qreq);
        });
        xassert_eqq($status, null);
        xassert_str_contains($out, 'id="menufinal" class="navitem active"');
        xassert_str_contains($out, 'href="https://www.iacr.org/submit/api/?action=download&amp;auth=X" download="' . htmlspecialchars($this->conf->opt("dbName")) . '.zip"');
        xassert_str_contains($out, 'href="' . $search_url . '"');
        xassert_str_ends_with($out, "</html>\n");
    }

    function test_download_url() {
        if (!file_exists(IACR_FinalPapers::HMAC_CONFIG)) {
            return;
        }
        // builds the URL only; nothing is sent to iacr.org
        $old_type = $this->conf->opt("iacrType");
        $this->conf->set_opt("iacrType", "crypto");
        $url = (new IACR_FinalPapers($this->conf))->api_url("download");
        $this->conf->set_opt("iacrType", $old_type);
        xassert_str_starts_with($url, "https://www.iacr.org/submit/api/?action=download&venue=crypto&");
        xassert_str_contains($url, "&shortName=" . urlencode($this->conf->opt("dbName")) . "&auth=");
    }

    function test_copyrights() {
        $old_type = $this->conf->opt("iacrType");
        $this->conf->set_opt("iacrType", "tosc");
        $page_with = function ($rows) {
            return function ($user, $qreq) use ($rows) {
                $page = new IACR_TestCopyrights_Page($user->conf);
                $page->rows = $rows;
                $page->print_page($qreq);
            };
        };

        list($status, $out) = $this->run_print($this->u_chair, "iacr_copyrights",
            $page_with([["paperId" => 1, "title" => "Lattices & <b>Friends</b>"], ["paperId" => 2, "title" => "Second"]]));
        xassert_eqq($status, null);
        xassert_str_contains($out, 'id="menucopyright" class="navitem active"');
        xassert_str_contains($out, "<ol>\n<li>Lattices &amp; &lt;b&gt;Friends&lt;/b&gt;</li>\n<li>Second</li>\n</ol>");
        xassert_str_ends_with($out, "</html>\n");

        list($status, $out) = $this->run_print($this->u_chair, "iacr_copyrights", $page_with([]));
        xassert_str_contains($out, "No copyright forms have been submitted.");
        xassert(!str_contains($out, "<ol>"));

        list($status, $out) = $this->run_print($this->u_chair, "iacr_copyrights", $page_with(null));
        xassert_str_contains($out, "Unable to read the copyright database.");
        xassert_str_ends_with($out, "</html>\n");        $this->conf->set_opt("iacrType", $old_type);
    }

    /** Final versions of papers 1 and 2 as iacr.org reports them.
     * @return list<array<string,mixed>> */
    private function remote_finals() {
        return [
            ["paperId" => "1", "title" => "Final title", "pages" => 12,
             "authorlist" => [["name" => "Ann A", "affiliation" => "U & V"],
                              ["name" => "Bob B", "affiliation" => "W"],
                              ["name" => "Cy C", "affiliation" => "X"]]],
            ["paperId" => "2", "title" => "Second final", "pages" => 20,
             "authorlist" => [["name" => "Dee D", "affiliation" => "Y"]]]
        ];
    }

    /** Make an IACR page object of class `$class` with final versions
     * `$remote` and LNCS work kept in `$dir`.
     * @template T
     * @param class-string<T> $class
     * @param ?list<array<string,mixed>> $remote
     * @param ?string $dir
     * @return T */
    private function test_page($class, Contact $user, $remote = null, $dir = null) {
        $page = in_array($class, [IACR_TestLncsData_Page::class, IACR_TestLncsFrontmatter_Page::class])
            ? new $class($user) : new $class($user->conf);
        if (property_exists($page, "source")) {
            $page->source = new IACR_TestFinalPapers($this->conf, $remote);
        }
        if (property_exists($page, "dir")) {
            $page->dir = $dir;
        }
        return $page;
    }

    /** Return output and HTTP status of `$f()`, run in test mode.
     * @return array{int,string} */
    private function capture(callable $f) {
        $old_test_mode = Navigation::$test_mode;
        Navigation::$test_mode = 1;
        $old_status = Navigation::http_response_code();
        Navigation::http_response_code(200);
        ob_start();
        try {
            $f();
        } finally {
            $out = ob_get_clean();
            $status = Navigation::http_response_code($old_status);
            Navigation::$test_mode = $old_test_mode;
        }
        return [$status, $out];
    }

    /** @return string */
    private function temp_dir() {
        $dir = sys_get_temp_dir() . "/hotcrp-iacr-test-" . getmypid() . "-" . mt_rand();
        mkdir($dir);
        return $dir;
    }

    /** @param string $dir */
    private function remove_dir($dir) {
        foreach (glob("{$dir}/*") as $f) {
            unlink($f);
        }
        rmdir($dir);
    }

    /** Mark papers `$pids` as having final versions on iacr.org.
     * @param list<int> $pids */
    private function set_final_uploaded($pids) {
        $this->conf->qe("delete from PaperOption where optionId=?", PaperOption::IACR_FINAL_ID);
        foreach ($pids as $pid) {
            $this->conf->qe("insert into PaperOption set paperId=?, optionId=?, value=1", $pid, PaperOption::IACR_FINAL_ID);
        }
    }

    function test_lncs() {
        $old_type = $this->conf->opt("iacrType");
        $this->conf->set_opt("iacrType", "crypto");
        $this->with_accepted_papers(function () {
            $this->conf->qe("update Paper set title='Lattices & <b>Friends</b>' where paperId=2");
            try {
                // papers without final versions are listed instead of the steps
                $this->set_final_uploaded([1]);
                list($status, $out) = $this->run_print($this->u_chair, "iacr_lncs", function ($user, $qreq) {
                    $this->test_page(IACR_TestLncs_Page::class, $user)->print_page($qreq);
                });
                xassert_eqq($status, null);
                xassert_str_contains($out, 'id="menulncs" class="navitem active"');
                xassert_str_contains($out, "have not uploaded their final versions");
                xassert_str_contains($out, '<li><a href="' . htmlspecialchars($this->conf->hoturl("paper", ["p" => 2])) . '">Lattices &amp; &lt;b&gt;Friends&lt;/b&gt;</a></li>');
                xassert(!str_contains($out, 'href="' . htmlspecialchars($this->conf->hoturl("paper", ["p" => 1])) . '"'));
                xassert(!str_contains($out, "Download Zip archive"));

                $this->set_final_uploaded([1, 2]);
                list($status, $out) = $this->run_print($this->u_chair, "iacr_lncs", function ($user, $qreq) {
                    $this->test_page(IACR_TestLncs_Page::class, $user)->print_page($qreq);
                });
                xassert_str_contains($out, "All final versions have been uploaded.");
                xassert_str_contains($out, 'href="https://www.iacr.org/submit/api/?action=download&amp;auth=X" download="' . htmlspecialchars($this->conf->opt("dbName")) . '.zip"');
                foreach (["iacr_author_emails", "iacr_lncs_editor", "iacr_frontmatter", "iacr_final_json"] as $page) {
                    xassert_str_contains($out, 'href="' . htmlspecialchars($this->conf->hoturl($page)) . '"');
                }
                xassert_str_ends_with($out, "</html>\n");
            } finally {
                $this->set_final_uploaded([]);
                $this->conf->qe("update Paper set title=? where paperId=2", $this->conf->checked_paper_by_id(2)->title);
            }
        });
        $this->conf->set_opt("iacrType", $old_type);
    }

    function test_author_emails() {
        xassert_eqq(IACR_AuthorEmails_Page::cell("a\tb\r\nc\nd"), 'a\tb\nc\nd');
        xassert_eqq(IACR_AuthorEmails_Page::cell(null), "");
        $this->with_accepted_papers(function () {
            $lines = explode("\r\n", IACR_AuthorEmails_Page::tsv($this->conf));
            xassert_eqq($lines[0], "paperId\tEmail\tGiven name\tFamily name\tTitle");
            xassert_eqq(array_pop($lines), "");
            $expected = [];
            foreach ([1, 2] as $pid) {
                $prow = $this->conf->checked_paper_by_id($pid);
                foreach ($prow->contact_list() as $u) {
                    $expected[] = join("\t", [$pid, $u->email, $u->firstName, $u->lastName, $prow->title]);
                }
            }
            xassert(count($expected) > 0);
            xassert_eqq(array_slice($lines, 1), $expected);
        });
    }

    function test_final_json() {
        list($status, $out) = $this->capture(function () {
            $this->test_page(IACR_TestFinalJson_Page::class, $this->u_chair, [["paperId" => "1", "title" => "T"]])->print_json();
        });
        xassert_eqq($status, 200);
        xassert_eqq(json_decode($out, true), [["paperId" => "1", "title" => "T"]]);
        list($status, $out) = $this->capture(function () {
            $this->test_page(IACR_TestFinalJson_Page::class, $this->u_chair, null)->print_json();
        });
        xassert_eqq($status, 502);
        xassert_eqq(json_decode($out, true), ["error" => "Unable to fetch data from www.iacr.org"]);
    }

    function test_frontmatter_people() {
        $fm = new IACR_FrontMatter($this->conf);
        $name = function ($p) { return $p["firstName"] . " " . $p["lastName"]; };
        $chairs = array_map($name, $fm->chairs());
        $pc = array_map($name, $fm->committee());
        $external = array_map($name, $fm->external_reviewers());
        $chair = $this->u_chair;
        $member = $this->u_pc;
        xassert(in_array("{$chair->firstName} {$chair->lastName}", $chairs));
        xassert(!in_array("{$chair->firstName} {$chair->lastName}", $pc));
        xassert(in_array("{$member->firstName} {$member->lastName}", $pc));
        xassert(!in_array("{$member->firstName} {$member->lastName}", $chairs));
        xassert_eqq(count($chairs) + count($pc), count(array_filter($this->conf->pc_members(), function ($u) {
            return ($u->roles & Contact::ROLE_PC) !== 0;
        })));
        xassert_eqq(array_values(array_intersect($external, array_merge($chairs, $pc))), []);
        xassert_eqq(IACR_FrontMatter::amp("A & B"), "A \\& B");
    }

    function test_frontmatter_external_reviewers() {
        // only external reviewers who submitted a review are thanked
        $emails = ["iacr-ext-done@_.com", "iacr-ext-asked@_.com"];
        xassert_assign($this->u_chair, "paper,action,user\n1,review,{$emails[0]}\n1,review,{$emails[1]}\n");
        foreach ($emails as $i => $email) {
            $this->conf->qe("update ContactInfo set firstName=?, lastName='IACR-reviewer' where email=?", "Ext{$i}", $email);
        }
        $done = $this->conf->checked_user_by_email($emails[0]);
        $asked = $this->conf->checked_user_by_email($emails[1]);
        $this->conf->qe("update PaperReview set reviewSubmitted=?, reviewNeedsSubmit=0 where paperId=1 and contactId=?", Conf::$now, $done->contactId);
        try {
            xassert_eqq($this->conf->fetch_ivalue("select reviewType from PaperReview where paperId=1 and contactId=?", $asked->contactId), REVIEW_EXTERNAL);
            $names = array_map(function ($p) {
                return $p["firstName"] . " " . $p["lastName"];
            }, (new IACR_FrontMatter($this->conf))->external_reviewers());
            xassert(in_array("Ext0 IACR-reviewer", $names));
            xassert(!in_array("Ext1 IACR-reviewer", $names));
        } finally {
            $this->conf->qe("delete from PaperReview where paperId=1 and contactId?a", [$done->contactId, $asked->contactId]);
            $this->conf->qe("delete from ContactInfo where contactId?a", [$done->contactId, $asked->contactId]);
            $this->conf->invalidate_caches(["users" => true]);
        }
    }

    function test_frontmatter() {
        list($status, $out) = $this->capture(function () {
            $this->test_page(IACR_TestFrontmatter_Page::class, $this->u_chair, $this->remote_finals())->print_latex();
        });
        xassert_eqq($status, 200);
        xassert_str_starts_with($out, "\\documentclass[runningheads]{llncs}\n\\usepackage{longtable}\n\\usepackage{multicol}\n");
        xassert_str_contains($out, "{$this->u_chair->firstName} {$this->u_chair->lastName} & ");
        xassert_str_contains($out, "\\addtocontents{toc}{\\protect\\section*{Add Topics}}\n");
        xassert_str_contains($out, "% author: Ann A, U & V\n% author: Bob B, W\n% author: Cy C, X\n"
            . "% This paper has 12 pages in the PDF\n"
            . "\\author{Ann A, Bob B and Cy C}\n% \\institute{U \\& V, W and X}\n"
            . "\\title{Final title}\n\\maketitle\n\\setcounter{page}{13}\n");
        xassert_str_contains($out, "\\author{Dee D}\n% \\institute{Y}\n\\title{Second final}\n\\maketitle\n\\setcounter{page}{33}\n");
        xassert_str_ends_with($out, "\\end{document}");

        list($status, $out) = $this->capture(function () {
            $this->test_page(IACR_TestFrontmatter_Page::class, $this->u_chair, null)->print_latex();
        });
        xassert_str_contains($out, "% Unable to fetch final versions from www.iacr.org\n");
        xassert_str_ends_with($out, "\\end{document}");
    }

    /** Run /iacr_lncs_data with `$qreq`.
     * @param ?list<array<string,mixed>> $remote
     * @param string $dir
     * @return array{int,string,IACR_TestLncsData_Page} */
    private function lncs_data(Qrequest $qreq, $remote, $dir) {
        $page = $this->test_page(IACR_TestLncsData_Page::class, $this->u_chair, $remote, $dir);
        list($status, $out) = $this->capture(function () use ($page, $qreq) {
            $page->run($qreq);
        });
        return [$status, $out, $page];
    }

    function test_lncs_data() {
        $dir = $this->temp_dir();
        $file = (new IACR_LncsWork($this->u_chair, $dir))->filename();
        $get = TestQreq::get_page("iacr_lncs_data");
        try {
            // iacr.org unreachable: error, nothing saved
            list($status, $out) = $this->lncs_data($get, null, $dir);
            xassert_eqq($status, 502);
            xassert_eqq(json_decode($out, true), ["error" => "Unable to fetch data from www.iacr.org"]);
            xassert(!file_exists($file));

            // first load starts from the final versions and saves them
            list($status, $out, $page) = $this->lncs_data($get, $this->remote_finals(), $dir);
            xassert_eqq($status, 200);
            $data = json_decode($out, true);
            xassert_eqq($data["venue"], $this->conf->opt("iacrType"));
            xassert_eqq($data["volumes"], []);
            xassert_eqq(array_column($data["unassigned_papers"], "title"), ["Final title", "Second final"]);
            xassert_eqq(file_get_contents($file), $out);

            // later loads return the saved work without asking iacr.org
            list($status, $out2, $page) = $this->lncs_data($get, [], $dir);
            xassert_eqq($out2, $out);
            xassert_eqq($page->source->fetches, 0);

            // saving needs the session token
            $work = json_encode(["volumes" => [["name" => "1", "topics" => []]], "unassigned_papers" => []]);
            $unapproved = TestQreq::unapproved_post(["json" => $work])->set_page("iacr_lncs_data");
            list($status, $out2) = $this->lncs_data($unapproved, [], $dir);
            xassert_eqq($status, 403);
            xassert(isset(json_decode($out2, true)["error"]));
            xassert_eqq(file_get_contents($file), $out);

            list($status, $out2) = $this->lncs_data(TestQreq::post_page("iacr_lncs_data", ["json" => $work]), [], $dir);
            xassert_eqq($status, 200);
            xassert_eqq($out2, "{}");
            xassert_eqq(file_get_contents($file), $work);

            foreach (["not json", "[1,2]", ""] as $bad) {
                list($status, $out2) = $this->lncs_data(TestQreq::post_page("iacr_lncs_data", ["json" => $bad]), [], $dir);
                xassert_eqq($status, 400);
                xassert(isset(json_decode($out2, true)["error"]));
            }
            xassert_eqq(file_get_contents($file), $work);

            // starting over deletes the saved work
            $delete = TestQreq::post_page("iacr_lncs_data", ["delete" => "true"]);
            list($status, $out2) = $this->lncs_data($delete, [], $dir);
            xassert_eqq(json_decode($out2, true), ["ok" => "Data was deleted"]);
            xassert(!file_exists($file));
            list($status, $out2) = $this->lncs_data($delete, [], $dir);
            xassert_eqq(json_decode($out2, true), ["ok" => "Data did not exist for this user"]);

            // work is per chair
            xassert_neqq((new IACR_LncsWork($this->u_pc, $dir))->filename(), $file);
        } finally {
            $this->remove_dir($dir);
        }
    }

    function test_lncs_frontmatter() {
        $dir = $this->temp_dir();
        try {
            $page = $this->test_page(IACR_TestLncsFrontmatter_Page::class, $this->u_chair, null, $dir);
            list($status, $out) = $this->capture(function () use ($page) { $page->print_latex(); });
            xassert_eqq($status, 404);
            xassert_str_contains($out, "LNCS editor first");

            list($p1, $p2) = $this->remote_finals();
            (new IACR_LncsWork($this->u_chair, $dir))->save(json_encode([
                "volumes" => [["name" => "1", "topics" => [["name" => "Lattices", "papers" => [$p2]]]]],
                "unassigned_papers" => [$p1]
            ]));
            list($status, $out) = $this->capture(function () use ($page) { $page->print_latex(); });
            xassert_eqq($status, 200);
            xassert_str_contains($out, "\\addtocontents{toc}{\\protect\\section*{Lattices}}\n% author: Dee D, Y\n");
            xassert_str_contains($out, "\\title{Second final}\n\\maketitle\n\\setcounter{page}{21}\n"
                . "\\addtocontents{toc}{\\protect\\section*{Uncategorized papers}}\n");
            xassert_str_contains($out, "\\title{Final title}\n\\maketitle\n\\setcounter{page}{33}\n");
            xassert_str_ends_with($out, "\\end{document}");
        } finally {
            $this->remove_dir($dir);
        }
    }

    function test_lncs_editor() {
        $this->with_accepted_papers(function () {
            list($status, $out) = $this->run_print($this->u_chair, "iacr_lncs_editor", function ($user, $qreq) {
                $this->test_page(IACR_TestLncsEditor_Page::class, $user)->print_page($qreq);
            });
            $qreq = Qrequest::$main_request;
            $data_url = json_encode($this->conf->hoturl("iacr_lncs_data"));
            xassert_eqq($status, null);
            xassert_str_starts_with($out, "<!DOCTYPE html>");
            xassert(!str_contains($out, "<?"));
            xassert_str_contains($out, '<link href="' . htmlspecialchars($qreq->navigation()->siteurl()) . 'stylesheets/style.css"');
            xassert_str_contains($out, "$.getJSON({$data_url}, function(data) {");
            xassert_eqq(substr_count($out, "url: {$data_url},"), 2);
            // errors from /iacr_lncs_data have HTTP error codes; show their messages
            xassert_eqq(substr_count($out, "$('#save_status').text((jqxhr.responseJSON && jqxhr.responseJSON.error) || textStatus);"), 2);
            xassert_eqq(substr_count($out, "'post': " . json_encode($qreq->post_value()) . "}"), 2);
            xassert_str_contains($out, 'href="' . htmlspecialchars($this->conf->hoturl("iacr_lncs_frontmatter")) . '" download="frontmatter.tex"');
            xassert_str_contains($out, 'href="' . str_replace("PAPERID", "{{paperId}}", htmlspecialchars($this->conf->hoturl("paper", ["p" => "PAPERID"]))) . '">View paper</a>');
            xassert_eqq(substr_count($out, 'style="cursor:pointer;" src="data:image/svg+xml;base64,'), 2);
            xassert_str_contains($out, "have not uploaded their final versions");
            xassert_str_ends_with($out, "</html>\n");
            // dark mode: iacr-pages.css also follows the editor's inline styles
            $this->check_stylesheet_order($out);
            xassert(strpos($out, "</style>") < strpos($out, "stylesheets/iacr-pages.css"));
        });
    }

    /** Check that a page loads HotCRP's style.css, then Bootstrap, then
     * stylesheets/iacr-pages.css, and prints no fixed theme by default.
     * @param string $out */
    private function check_stylesheet_order($out) {
        $style = strpos($out, "stylesheets/style.css");
        $bootstrap = strpos($out, "https://iacr.org/libs/css/bootstrap/bootstrap-5.3/css/bootstrap.min.css");
        $iacr = strpos($out, "stylesheets/iacr-pages.css");
        xassert($style !== false && $bootstrap !== false && $iacr !== false);
        xassert($style < $bootstrap && $bootstrap < $iacr);
        xassert(file_exists(SiteLoader::$root . "/stylesheets/iacr-pages.css"));
        xassert(!str_contains($out, 'id="p-theme"'));
        xassert_str_contains($out, '<script src="https://iacr.org/libs/css/bootstrap/bootstrap-5.3/js/bootstrap.bundle.min.js"></script>');
    }

    function test_bootstrap_5() {
        // live IACR pages use Bootstrap 5.3, not Bootstrap 4 URLs or markup
        $root = SiteLoader::$root;
        $files = array_merge(glob("{$root}/src/pages/p_iacr*.php"), glob("{$root}/src/pages/iacr/*.inc"),
                             glob("{$root}/src/pages/iacrcopyright/*.html"));
        xassert(count($files) > 10);
        foreach ($files as $f) {
            $s = file_get_contents($f);
            xassert(!str_contains($s, "bootstrap/dist/"));
            xassert_eqq(preg_match('/data-(toggle|dismiss|target)=|\.modal\(\)|class="([^"]*\s)?(float-(left|right)|m[lr]-[0-5]|font-weight-bold|close|form-group)[\s"]/', $s), 0);
        }
        foreach (glob("{$root}/src/pages/iacrcopyright/*.html") as $f) {
            xassert_str_contains(file_get_contents($f), "https://iacr.org/libs/css/bootstrap/bootstrap-5.3/css/bootstrap.min.css");
        }
    }

    /** Run the /iacr redirect page for URL path `$path`.
     * @param ?string $path
     * @return array{?int,?string} redirect status and URL, or the
     *   failure status and null */
    private function run_redirect($path) {
        $qreq = TestQreq::get_page("iacr")->set_path($path)->set_user($this->u_pc);
        Qrequest::set_main_request($qreq);
        $old_test_mode = Navigation::$test_mode;
        Navigation::$test_mode = 1;
        ob_start();
        try {
            IACR_Redirect_Page::go($this->u_pc, $qreq);
            return [null, null];
        } catch (Redirection $redir) {
            return [$redir->status, $redir->url];
        } catch (PageCompletion $pc) {
            return [Navigation::http_response_code(), null];
        } finally {
            ob_end_clean();
            Navigation::$test_mode = $old_test_mode;
        }
    }

    function test_old_urls_redirect() {
        $pages = $this->conf->page_components($this->u_chair, TestQreq::get());
        xassert_eqq($pages->get("iacr")->print_function, "IACR_Redirect_Page::go");
        // /iacr, /iacr/, and /iacr/index.php go home
        foreach ([null, "", "/", "/index.php"] as $path) {
            xassert_eqq($this->run_redirect($path), [301, $this->conf->hoturl("iacr_home")]);
        }
        // every old script goes to an existing page; anyone may follow
        // the redirect, and the page itself checks access
        foreach (IACR_Redirect_Page::OLD_SCRIPTS as $script => $page) {
            xassert(!!$pages->get($page));
            if ($script !== "") {
                xassert(file_exists(SiteLoader::$root . "/iacr/deprecated/{$script}"));
                xassert_eqq($this->run_redirect("/{$script}"), [301, $this->conf->hoturl($page)]);
            }
        }
        // the old data scripts, the IACR API, and unknown names are not redirected
        foreach (["/getLNCSData.php", "/saveLNCSData.php", "/api/paper.php",
                  "/api/", "/lncs.php/x", "/LNCS.PHP", "/nope.php", "/iacr_home"] as $path) {
            xassert_eqq($this->run_redirect($path), [404, null]);
        }
        // the stubs used on servers that run iacr/*.php directly agree
        $nstubs = 0;
        foreach (glob(SiteLoader::$root . "/iacr/*.php") as $f) {
            if (preg_match('/Location: \.\.\/(iacr_\w+)"/', file_get_contents($f), $m)) {
                xassert_eqq(IACR_Redirect_Page::target_page("/" . basename($f)), $m[1]);
                ++$nstubs;
            }
        }
        xassert_eqq($nstubs, 7);
    }

    function test_old_scripts_moved() {
        // converted or unused scripts are kept in iacr/deprecated
        $root = SiteLoader::$root;
        foreach (["index.php", "committee.php", "updatecoll.php", "accepted.php",
                  "program.php", "downloadaccepted.php", "final.php", "copyright.php",
                  "lncs.php", "lncsEditor.php", "getSpreadsheet.php", "getFrontMatter.php",
                  "getFinalPaperJson.php", "getLNCSData.php", "saveLNCSData.php",
                  "createFrontMatter.php"] as $f) {
            xassert(file_exists("{$root}/iacr/deprecated/{$f}"));
        }
        foreach (["committee.php", "updatecoll.php", "downloadaccepted.php", "getSpreadsheet.php",
                  "getFrontMatter.php", "getFinalPaperJson.php", "getLNCSData.php",
                  "saveLNCSData.php", "createFrontMatter.php"] as $f) {
            xassert(!file_exists("{$root}/iacr/{$f}"));
        }
        // converted pages redirect from their old URLs
        foreach (["index.php" => "iacr_home", "accepted.php" => "iacr_accepted",
                  "program.php" => "iacr_program", "final.php" => "iacr_final",
                  "copyright.php" => "iacr_copyrights", "lncs.php" => "iacr_lncs",
                  "lncsEditor.php" => "iacr_lncs_editor"] as $f => $page) {
            xassert_str_contains(file_get_contents("{$root}/iacr/{$f}"), "Location: ../{$page}\"");
            xassert(!!$this->conf->page_components($this->u_chair, TestQreq::get())->get($page));
        }
        // support files for the old scripts are kept out of iacr/deprecated
        // itself, so the old scripts there can't run
        foreach (["init.php", "finalLib.php", "ellipsize.php", "includes/header.inc",
                  "includes/leftnav.inc", "includes/frontmatter1.txt", "includes/plus.svg"] as $f) {
            xassert(!file_exists("{$root}/iacr/{$f}"));
            xassert(!file_exists("{$root}/iacr/deprecated/{$f}"));
            xassert(file_exists("{$root}/iacr/deprecated/support/{$f}"));
        }
        // files still used outside the IACR pages stay in iacr
        foreach (["collaborators.inc", "open_webhook.php", "pdf_webhook.php",
                  "user_webhook.php", "settings_webhook.php", "api/lib.php"] as $f) {
            xassert(file_exists("{$root}/iacr/{$f}"));
        }
        // old pages link to converted pages
        $leftnav = file_get_contents("{$root}/iacr/deprecated/support/includes/leftnav.inc");
        foreach (["iacr_home", "iacr_accepted", "iacr_program", "iacr_final", "iacr_copyrights", "iacr_lncs"] as $page) {
            xassert_str_contains($leftnav, "href=\"../{$page}\"");
        }
    }
}
