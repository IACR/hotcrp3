<?php
// pages/p_iacr_finaljson.php -- final-version metadata as JSON (/iacr_final_json)
// Part of the IACR extensions; not part of upstream HotCRP.
// Replaces iacr/getFinalPaperJson.php.

require_once(__DIR__ . "/iacr/finalpapers.php");

class IACR_FinalJson_Page {
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
        $data = $this->final_papers_source()->final_papers();
        if ($data === null) {
            Navigation::http_response_code(502);
            $data = ["error" => "Unable to fetch data from www.iacr.org"];
        }
        Navigation::header("Content-Type: application/json; charset=utf-8");
        echo json_encode($data);
    }
}
