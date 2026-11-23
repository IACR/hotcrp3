<?php
// src/pages/iacr/finalpapers.php -- final-version data for IACR chair pages
// Part of the IACR extensions; not part of upstream HotCRP.
//
// Final versions are uploaded to www.iacr.org, not HotCRP. This fetches
// their metadata from the iacr.org submission API and adds each paper's
// HotCRP topics. Replaces getFinalPaperData() in the old
// iacr/deprecated/support/finalLib.php.

class IACR_FinalPapers {
    /** @var Conf */
    protected $conf;

    /** Shared secrets for signing iacr.org API requests; defines
     * get_hmac() and get_conf_message(). */
    const HMAC_CONFIG = "/var/www/util/hotcrp/hmac.php";

    function __construct(Conf $conf) {
        $this->conf = $conf;
    }

    /** @return Conf */
    function conf() {
        return $this->conf;
    }

    /** Return HotCRP topic names for each accepted paper.
     * @return array<int,list<string>> */
    function topic_map() {
        $map = [];
        $result = $this->conf->qe("select Paper.paperId, TopicArea.topicName
            from Paper
            join PaperTopic on (PaperTopic.paperId=Paper.paperId)
            join TopicArea on (TopicArea.topicId=PaperTopic.topicId)
            where Paper.outcome>0 and Paper.timeWithdrawn=0");
        while (($row = $result->fetch_row())) {
            $map[(int) $row[0]][] = $row[1];
        }
        Dbl::free($result);
        return $map;
    }

    /** Return accepted papers with no final version on iacr.org yet. The
     * iacr.org API (iacr/api/updatePaper.php) sets the paper's
     * PaperOption::IACR_FINAL_ID field when a final version is uploaded.
     * @return list<array{paperId:int,title:string}> */
    function missing_final_versions() {
        $missing = [];
        $result = $this->conf->qe("select paperId, title from Paper
            where outcome>0 and timeWithdrawn=0
            and paperId not in (select paperId from PaperOption where optionId=?)
            order by paperId", PaperOption::IACR_FINAL_ID);
        while (($row = $result->fetch_row())) {
            $missing[] = ["paperId" => (int) $row[0], "title" => $row[1]];
        }
        Dbl::free($result);
        return $missing;
    }

    /** Return the iacr.org API URL for `$action` ("view" or "download"),
     * signed for this conference.
     * @param string $action
     * @return string */
    function api_url($action) {
        require_once(self::HMAC_CONFIG);
        $db = $this->conf->opt("dbName");
        $type = $this->conf->opt("iacrType");
        $year = $this->conf->opt("year");
        return "https://www.iacr.org/submit/api/?" . http_build_query([
            "action" => $action, "venue" => $type, "year" => $year, "shortName" => $db,
            "auth" => get_hmac(get_conf_message($db, $type, $year))
        ]);
    }

    /** Fetch final-version metadata from iacr.org, or null on failure.
     * This does not check that every paper has a final version, so it
     * may be incomplete. (The API does not serve CiC.)
     * @return ?list<array<string,mixed>> */
    protected function fetch_remote() {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_URL, $this->api_url("view"));
        $output = curl_exec($ch);
        curl_close($ch);
        return self::parse_remote($output);
    }

    /** Parse an iacr.org API response. It should be a JSON list of papers;
     * anything else, such as an error object, is a failure (null).
     * @param mixed $output
     * @return ?list<array<string,mixed>> */
    static function parse_remote($output) {
        $data = is_string($output) ? json_decode($output, true) : null;
        if (!is_array($data) || !array_is_list($data)) {
            return null;
        }
        foreach ($data as $paper) {
            if (!is_array($paper)) {
                return null;
            }
        }
        return $data;
    }

    /** Return final-version metadata with a `topics` list added for each
     * paper that has HotCRP topics, or null if iacr.org could not be read.
     * @return ?list<array<string,mixed>> */
    function final_papers() {
        if (($papers = $this->fetch_remote()) === null) {
            return null;
        }
        $topics = $this->topic_map();
        foreach ($papers as &$paper) {
            if (isset($topics[$paper["paperId"] ?? null])) {
                $paper["topics"] = $topics[$paper["paperId"]];
            }
        }
        unset($paper);
        return $papers;
    }
}
