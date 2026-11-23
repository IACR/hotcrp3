<?php
// pages/p_iacr_accepted.php -- export the accepted papers (/iacr_accepted)
// Part of the IACR extensions; not part of upstream HotCRP.
// Replaces iacr/accepted.php.

class IACR_Accepted_Page {
    static function go(Contact $user, Qrequest $qreq) {
        IACR_Page::check_chair($user, $qreq);
        $conf = $user->conf;
        $site = $conf->opt("conferenceSite") ?? "";
        IACR_Page::print_header($qreq, "IACR functionality of HotCRP", "accepted");
        echo '<h3>Export list of accepted papers</h3>
<p>
  Once the acceptance decisions are finalized, you should
  list the accepted papers on the website.  The way to do this
  is to download the <tt>papers.json</tt> file and place it in the
  location <tt>', htmlspecialchars($site . "/json/papers.json"), '</tt>
</p>
<p>
  <a class="button button-primary" target="_blank" href="', htmlspecialchars($conf->hoturl("iacr_accepted_json")), '" download="papers.json">Download papers.json</a>
</p>
';
        IACR_Page::print_footer();
    }
}
