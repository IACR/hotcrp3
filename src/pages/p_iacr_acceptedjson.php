<?php
// pages/p_iacr_acceptedjson.php -- accepted papers as papers.json (/iacr_accepted_json)
// Part of the IACR extensions; not part of upstream HotCRP.
// Replaces iacr/downloadaccepted.php.
//
// The output is placed on the conference website (see /iacr_accepted) and
// is read by the IACR program editor (see /iacr_program), so its format
// must not change.

require_once(__DIR__ . "/iacr/finalpapers.php");

class IACR_AcceptedJson_Page {
    /** @var Conf */
    protected $conf;

    function __construct(Conf $conf) {
        $this->conf = $conf;
    }

    static function go(Contact $user, Qrequest $qreq) {
        IACR_Page::check_chair($user, $qreq);
        (new static($user->conf))->print_json();
    }

    /** @return IACR_FinalPapers */
    protected function final_papers_source() {
        return new IACR_FinalPapers($this->conf);
    }

    function print_json() {
        $data = $this->papers_json();
        if ($data === null) {
            Navigation::http_response_code(502);
            $data = ["error" => "Unable to fetch data from www.iacr.org"];
        }
        Navigation::header("Content-Type: application/json; charset=utf-8");
        echo json_encode($data, JSON_PRETTY_PRINT);
    }

    /** Return the papers.json data: accepted, unwithdrawn papers from
     * HotCRP, with titles, abstracts, and other details replaced by their
     * final versions from iacr.org where available. Returns null if
     * iacr.org could not be read.
     *
     * For journals, each `paperId` is "<dbName>_<paperId>". The program
     * editor combines papers from several journal volumes, each with its
     * own HotCRP instance, and this keeps their IDs distinct. Final
     * versions are still matched by plain HotCRP paper ID. The iacr.org API
     * does not serve CiC, so CiC output has no final-version data.
     * @return ?array<string,mixed> */
    function papers_json() {
        $type = $this->conf->opt("iacrType");
        $journal = in_array($type, ["tches", "tosc", "cic"], true);
        $id_prefix = $journal ? $this->conf->opt("dbName") . "_" : "";

        $final = [];
        if ($type !== "cic") {
            if (($fps = $this->final_papers_source()->final_papers()) === null) {
                return null;
            }
            foreach ($fps as $fp) {
                if (isset($fp["paperId"])) {
                    $final[(string) $fp["paperId"]] = $fp;
                }
            }
        }

        $papers = [];
        $result = $this->conf->qe("select paperId, title, authorInformation, abstract from Paper where outcome>0 and timeWithdrawn=0");
        while (($row = $result->fetch_assoc())) {
            $paper = [
                "paperId" => $id_prefix . $row["paperId"],
                "title" => $row["title"],
                "abstract" => $row["abstract"],
                "authors" => [],
                "affiliations" => []
            ];
            $orcids = [];
            foreach (preg_split('/\n/', $row["authorInformation"], -1, PREG_SPLIT_NO_EMPTY) as $line) {
                $au = Author::make_tabbed($line);
                $paper["authors"][] = $au->firstName . " " . $au->lastName;
                $paper["affiliations"][] = $au->affiliation;
                $orcids[] = $au->orcid;
            }
            $paper["affiliations"] = array_keys(array_fill_keys($paper["affiliations"], true));
            // ORCID iDs, parallel to `authors` ("" for none); only present
            // when some author has one, so other papers are unchanged
            if (array_filter($orcids, "strlen")) {
                $paper["orcids"] = $orcids;
            }
            if (($fp = $final[$row["paperId"]] ?? null)) {
                $paper["title"] = $fp["title"] ?? null;
                $paper["abstract"] = $fp["abstract"] ?? null;
                if (isset($fp["pubkey"])) {
                    $paper["pubkey"] = $fp["pubkey"];
                }
                if (isset($fp["topics"])) {
                    $paper["keywords"] = implode(", ", $fp["topics"]);
                }
                if (isset($fp["pages"])) {
                    $paper["pages"] = $fp["pages"];
                }
            }
            $papers[] = $paper;
        }
        Dbl::free($result);
        return ["_source" => "IACR/hotcrp v2", "acceptedPapers" => $papers];
    }
}
