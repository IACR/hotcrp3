<?php
// orcidrecord.php -- HotCRP class for a public ORCID record's name
// Part of the IACR extensions; not part of upstream HotCRP.

class OrcidRecord {
    /** @var string */
    public $orcid;
    /** @var 0|1|2|3 */
    public $status;
    /** @var string */
    public $given = "";
    /** @var string */
    public $family = "";
    /** @var string */
    public $credit = "";
    /** @var list<string> */
    public $others = [];

    const UNAVAILABLE = 0; // network error, timeout, lookups disabled
    const FOUND = 1;
    const NOT_FOUND = 2;
    const NAME_HIDDEN = 3; // record exists, but its name is private

    /** @param string $orcid
     * @param 0|1|2|3 $status */
    function __construct($orcid, $status) {
        $this->orcid = $orcid;
        $this->status = $status;
    }

    /** @return string */
    function display_name() {
        if ($this->credit !== "") {
            return $this->credit;
        }
        return trim("{$this->given} {$this->family}");
    }

    /** @return list<string> */
    function full_names() {
        $n = [];
        if ($this->given !== "" || $this->family !== "") {
            $n[] = "{$this->given} {$this->family}";
            $n[] = "{$this->family} {$this->given}";
        }
        if ($this->credit !== "") {
            $n[] = $this->credit;
        }
        foreach ($this->others as $o) {
            $n[] = $o;
        }
        return $n;
    }
}
