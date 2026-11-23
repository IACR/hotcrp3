<?php
// pages/p_iacr_home.php -- IACR chair overview page (/iacr_home)
// Part of the IACR extensions; not part of upstream HotCRP.
// Replaces iacr/index.php.

class IACR_Home_Page {
    static function go(Contact $user, Qrequest $qreq) {
        IACR_Page::check_chair($user, $qreq);
        $conf = $user->conf;
        IACR_Page::print_header($qreq, "IACR functionality of HotCRP", "home");
        echo '<h3>IACR extensions to HotCRP</h3>
<p>
  This section provides extra functionality that is specific to IACR
  conferences. Other help items for HotCRP can be found in the <a href="', htmlspecialchars($conf->hoturl("help")), '">help
  section</a> of HotCRP.
</p>
<p>
  <strong>Before opening the site to submissions</strong>,
  you should perform the following steps using the menu on the left:
</p>
<ul>
  <li>Create accounts for your program committee.</li>
  <li>Import the recent collaborators of your program committee</li>
</ul>
<p>
  After you have finalized acceptance decisions, you should turn on
  <strong>Collect final versions of accepted submissions</strong> in
  the <a href="', htmlspecialchars($conf->hoturl("settings", ["group" => "decisions"])), '"><tt>Settings -&gt; Decisions</tt></a>
  section. Authors of accepted
  papers will then see links for a copyright form and a link to the
  IACR submission server to upload their final versions. We do not use
  HotCRP to collect final versions of papers.
</p>
<p>
  Once decisions are final, you should perform the following additional tasks
  on the submission server:
</p>
<ul>
  <li>Announce the list of accepted papers on the conference website.</li>
  <li>Prepare the proceedings (if you are publishing with LNCS)</li>
  <li>Prepare the program for the web site</li>
</ul>
';
        IACR_Page::print_footer();
    }
}
