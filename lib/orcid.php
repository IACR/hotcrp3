<?php
// orcid.php -- HotCRP helper functions for ORCID iDs
// Part of the IACR extensions; not part of upstream HotCRP.

class Orcid {
    /** Return the canonical form (`0000-0002-1825-0097`) of ORCID iD `$s`,
     * `""` if `$s` is empty, or `null` if `$s` is not a valid ORCID iD.
     * Accepts orcid.org URLs, missing hyphens, and lowercase `x`, and
     * checks the ISO 7064 MOD 11-2 check digit.
     * @param ?string $s
     * @return ?string */
    static function clean($s) {
        $s = trim($s ?? "");
        if ($s === "") {
            return "";
        }
        if (!preg_match('/\A(?:(?:https?:\/\/)?(?:www\.)?orcid\.org\/)?(\d{4})-?(\d{4})-?(\d{4})-?(\d{3}[\dX])\/?\z/i', $s, $m)) {
            return null;
        }
        $d = $m[1] . $m[2] . $m[3] . strtoupper($m[4]);
        if (!self::check_digit_ok($d)) {
            return null;
        }
        return substr($d, 0, 4) . "-" . substr($d, 4, 4) . "-" . substr($d, 8, 4) . "-" . substr($d, 12);
    }

    /** @param string $d
     * @return bool */
    static function check_digit_ok($d) {
        if (strlen($d) !== 16) {
            return false;
        }
        $t = 0;
        for ($i = 0; $i !== 15; ++$i) {
            $t = ($t + (int) $d[$i]) * 2;
        }
        $r = (12 - $t % 11) % 11;
        return $d[15] === ($r === 10 ? "X" : (string) $r);
    }

    /** Return true iff `$s` is a canonical ORCID iD.
     * @param ?string $s
     * @return bool */
    static function is_canonical($s) {
        return $s !== null
            && strlen($s) === 19
            && self::clean($s) === $s;
    }
}
