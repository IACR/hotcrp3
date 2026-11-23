<?php
// pages/p_iacr_lncsdata.php -- load and save LNCS editor work (/iacr_lncs_data)
// Part of the IACR extensions; not part of upstream HotCRP.
// Replaces iacr/getLNCSData.php (GET) and iacr/saveLNCSData.php (POST).
// Called only by the LNCS editor (/iacr_lncs_editor).
//
// GET returns the chair's saved work, first creating it from the iacr.org
// final versions if there is none. POST with `json` saves new work; POST
// with `delete` discards it. POSTs need HotCRP's `post` token.

require_once(__DIR__ . "/iacr/finalpapers.php");
require_once(__DIR__ . "/iacr/lncswork.php");

class IACR_LncsData_Page {
    /** @var Contact */
    protected $user;

    function __construct(Contact $user) {
        $this->user = $user;
    }

    static function go(Contact $user, Qrequest $qreq) {
        IACR_Page::check_chair($user, $qreq);
        (new static($user))->run($qreq);
    }

    /** @return IACR_LncsWork */
    protected function work() {
        return new IACR_LncsWork($this->user);
    }

    /** @return IACR_FinalPapers */
    protected function final_papers_source() {
        return new IACR_FinalPapers($this->user->conf);
    }

    function run(Qrequest $qreq) {
        Navigation::header("Content-Type: application/json; charset=utf-8");
        if ($qreq->is_post()) {
            $j = $this->post($qreq);
        } else {
            $j = $this->get();
        }
        if (is_string($j)) {
            echo $j;
        } else if (empty($j)) {
            echo "{}";
        } else {
            if (isset($j["error"])) {
                Navigation::http_response_code($j["status"] ?? 400);
                unset($j["status"]);
            }
            echo json_encode($j);
        }
    }

    /** @return string|array<string,mixed> */
    private function get() {
        $work = $this->work();
        if (($s = $work->load()) !== null) {
            return $s;
        }
        if (($papers = $this->final_papers_source()->final_papers()) === null) {
            return ["error" => "Unable to fetch data from www.iacr.org", "status" => 502];
        }
        $conf = $this->user->conf;
        $s = json_encode([
            "venue" => $conf->opt("iacrType"),
            "year" => $conf->opt("year"),
            "unassigned_papers" => $papers,
            "volumes" => []
        ], JSON_PRETTY_PRINT);
        if (!$work->save($s)) {
            return ["error" => "Unable to save", "status" => 500];
        }
        return $s;
    }

    /** @return array<string,mixed> */
    private function post(Qrequest $qreq) {
        if (!$qreq->valid_post()) {
            return ["error" => "Missing or stale session token; reload the page", "status" => 403];
        }
        $work = $this->work();
        if (($json = $qreq->json ?? "") === "") {
            if (!isset($qreq->delete)) {
                return ["error" => "Missing parameter"];
            } else if ($work->load() === null) {
                return ["ok" => "Data did not exist for this user"];
            } else if (!$work->delete()) {
                return ["error" => "Unable to delete", "status" => 500];
            }
            return ["ok" => "Data was deleted"];
        }
        $data = json_decode($json, true);
        if (!is_array($data) || !isset($data["volumes"]) || !isset($data["unassigned_papers"])) {
            return ["error" => "Unable to parse parameter"];
        }
        if (!$work->save($json)) {
            return ["error" => "Unable to save", "status" => 500];
        }
        return [];
    }
}
