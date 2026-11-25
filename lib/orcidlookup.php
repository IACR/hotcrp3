<?php
// orcidlookup.php -- HotCRP helper for checking ORCID iDs against the
// public ORCID registry (pub.orcid.org; no API key needed)
// Part of the IACR extensions; not part of upstream HotCRP.

class OrcidLookup {
    /** Replacement fetcher for tests: `function (string $orcid): array{int,?string}`
     * returns an HTTP status (0 for failure) and response body.
     * @var ?callable */
    static public $fetcher;
    /** @var array<string,OrcidRecord> */
    static private $memo = [];
    /** @var float */
    static private $time_used = 0.0;

    // Lookups must never make saving slow: each request is short, and a
    // page load stops looking up once it has spent `TIME_BUDGET` seconds.
    const TIMEOUT = 3;
    const CONNECT_TIMEOUT = 2;
    const TIME_BUDGET = 6.0;
    const BASE_URL = "https://pub.orcid.org/v3.0/";

    /** Lookups run for web requests (and in tests with a fake fetcher), not
     * for command-line batch scripts, which may check many iDs at once.
     * @return bool */
    static function enabled() {
        return self::$fetcher !== null || PHP_SAPI !== "cli";
    }

    static function reset() {
        self::$memo = [];
        self::$time_used = 0.0;
    }

    /** @param string $orcid canonical ORCID iD
     * @return OrcidRecord */
    static function lookup($orcid) {
        if (isset(self::$memo[$orcid])) {
            return self::$memo[$orcid];
        }
        if (!Orcid::is_canonical($orcid)
            || !self::enabled()
            || self::$time_used >= self::TIME_BUDGET) {
            return new OrcidRecord($orcid, OrcidRecord::UNAVAILABLE);
        }
        $t0 = microtime(true);
        list($status, $body) = self::$fetcher
            ? call_user_func(self::$fetcher, $orcid)
            : self::fetch($orcid);
        self::$time_used += microtime(true) - $t0;
        $rec = self::parse($orcid, $status, $body);
        self::$memo[$orcid] = $rec;
        return $rec;
    }

    /** @param string $orcid
     * @return array{int,?string} */
    static private function fetch($orcid) {
        $curlh = curl_init();
        curl_setopt($curlh, CURLOPT_URL, self::BASE_URL . $orcid . "/personal-details");
        curl_setopt($curlh, CURLOPT_HTTPHEADER, ["Accept: application/json"]);
        curl_setopt($curlh, CURLOPT_USERAGENT, "HotCRP");
        curl_setopt($curlh, CURLOPT_TIMEOUT, self::TIMEOUT);
        curl_setopt($curlh, CURLOPT_CONNECTTIMEOUT, self::CONNECT_TIMEOUT);
        curl_setopt($curlh, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curlh, CURLOPT_FOLLOWLOCATION, false);
        $body = curl_exec($curlh);
        $status = curl_errno($curlh) ? 0 : curl_getinfo($curlh, CURLINFO_RESPONSE_CODE);
        curl_close($curlh);
        return [$status, is_string($body) ? $body : null];
    }

    /** @param string $orcid
     * @param int $status
     * @param ?string $body
     * @return OrcidRecord */
    static function parse($orcid, $status, $body) {
        if ($status === 404) {
            return new OrcidRecord($orcid, OrcidRecord::NOT_FOUND);
        }
        $j = $status === 200 && $body !== null ? json_decode($body) : null;
        if (!is_object($j)) {
            return new OrcidRecord($orcid, OrcidRecord::UNAVAILABLE);
        }
        $rec = new OrcidRecord($orcid, OrcidRecord::FOUND);
        $name = $j->name ?? null;
        if (is_object($name)) {
            $rec->given = self::value_of($name->{"given-names"} ?? null);
            $rec->family = self::value_of($name->{"family-name"} ?? null);
            $rec->credit = self::value_of($name->{"credit-name"} ?? null);
        }
        $others = $j->{"other-names"}->{"other-name"} ?? null;
        foreach (is_array($others) ? $others : [] as $o) {
            if (is_object($o) && is_string($o->content ?? null)
                && ($c = simplify_whitespace($o->content)) !== "") {
                $rec->others[] = $c;
            }
        }
        if ($rec->given === "" && $rec->family === ""
            && $rec->credit === "" && empty($rec->others)) {
            $rec->status = OrcidRecord::NAME_HIDDEN;
        }
        return $rec;
    }

    /** @param mixed $x
     * @return string */
    static private function value_of($x) {
        if (is_object($x) && is_string($x->value ?? null)) {
            return simplify_whitespace($x->value);
        }
        return "";
    }

    /** @param string $s
     * @return list<string> */
    static function name_tokens($s) {
        $s = strtolower(UnicodeHelper::deaccent($s));
        $s = preg_replace('/[^a-z0-9]+/', " ", $s);
        $t = trim($s);
        return $t === "" ? [] : explode(" ", $t);
    }

    /** Return true iff `$first $last` plausibly names the person in `$rec`.
     * Every token of the family name must appear in one of the record’s
     * names, and the first given-name initial (if any) must match another
     * token of that name. Order, accents, case, and hyphens are ignored, so
     * family-name-first and transliterated names usually match.
     * @param string $first
     * @param string $last
     * @return bool */
    static function name_matches(OrcidRecord $rec, $first, $last) {
        if ($rec->status !== OrcidRecord::FOUND) {
            return true;
        }
        $lt = self::name_tokens($last);
        $ft = self::name_tokens($first);
        if (empty($lt)) {
            $lt = $ft;
            $ft = [];
        }
        if (empty($lt)) {
            return true;
        }
        foreach ($rec->full_names() as $fullname) {
            $rt = self::name_tokens($fullname);
            if (array_diff($lt, $rt)) {
                continue;
            }
            if (empty($ft)) {
                return true;
            }
            $rest = array_values(array_diff($rt, $lt));
            if (empty($rest)) {
                return true; // record has no given name to compare
            }
            foreach ($rest as $tok) {
                if ($tok[0] === $ft[0][0]) {
                    return true;
                }
            }
        }
        return false;
    }

    /** Return a warning message if `$orcid` does not seem to belong to the
     * person named `$first $last`, or null if it does (or if that cannot be
     * determined).
     * @param string $orcid
     * @param string $first
     * @param string $last
     * @return ?string */
    static function mismatch_message($orcid, $first, $last) {
        $rec = self::lookup($orcid);
        if ($rec->status === OrcidRecord::NOT_FOUND) {
            return "<0>There is no ORCID record for {$orcid}. Please check the iD";
        } else if (!self::name_matches($rec, $first, $last)) {
            $rn = $rec->display_name();
            if ($rn === "" && !empty($rec->others)) {
                $rn = $rec->others[0];
            }
            return "<0>ORCID iD {$orcid} belongs to “{$rn}”. Please check that it is correct";
        }
        return null;
    }
}
