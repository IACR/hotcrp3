<?php
// pages/p_iacr_final.php -- final versions on iacr.org (/iacr_final)
// Part of the IACR extensions; not part of upstream HotCRP.
// Replaces iacr/final.php.

require_once(__DIR__ . "/iacr/finalpapers.php");

class IACR_Final_Page {
    /** @var Conf */
    protected $conf;

    function __construct(Conf $conf) {
        $this->conf = $conf;
    }

    static function go(Contact $user, Qrequest $qreq) {
        IACR_Page::check_chair($user, $qreq);
        (new static($user->conf))->print_page($qreq);
    }

    /** Return the signed iacr.org URL for a zip archive of all final
     * versions. The chair's browser fetches it directly.
     * @return string */
    protected function download_url() {
        return (new IACR_FinalPapers($this->conf))->api_url("download");
    }

    function print_page(Qrequest $qreq) {
        $search_url = $this->conf->hoturl("search", ["q" => "", "t" => "acc"]) . "#view";
        IACR_Page::print_header($qreq, "IACR functionality of HotCRP", "final");
        echo '<h3>Final papers</h3>
<p>
  Once you open the papers for final submission, the authors will be able
  to complete their IACR copyright form and upload the final versions of
  their papers.  These final versions will be submitted to www.iacr.org
  rather than to HotCRP itself, so that we can collect the final versions
  for the IACR archive and provide you with an interface to download the
  LNCS table of contents (see the left menu).
</p>
<p>
  You can view which authors have uploaded their final versions the same
  way you can view who has signed the copyright forms, namely by
  <a href="', htmlspecialchars($search_url), '">searching for accepted papers</a>
  and selecting "View options" to see how many have uploaded their final versions.
</p>
<p>
  You can
  <a style="font-weight: 600;" target="_blank" href="', htmlspecialchars($this->download_url()),
        '" download="', htmlspecialchars(($this->conf->opt("dbName") ?? "final") . ".zip"),
        '">Download a Zip archive</a> of all final versions of papers. (warning: this is slow).
</p>
';
        IACR_Page::print_footer();
    }
}
