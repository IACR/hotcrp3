<?php
require_once(dirname(__DIR__)."/iacr/init.php");
require "includes/header.inc";
?>
<div class="container-fluid float-left">
  <div class="row">
    <div class="col-4 col-lg-3 col-xl-2">
      <?php include "includes/leftnav.inc";?>
    </div>
    <div class="col-8">
      <h3>IACR extensions to HotCRP</h3>
      <p>
        This section provides extra functionality that is specific to IACR
        conferences. Other help items for HotCRP can be found in the <a href="../help">help
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
        the <a href="../settings/decisions"><tt>Settings -&gt; Decisions</tt></a>
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
    </div>
  </div>
</div>
<script>
 setActiveMenu('menuhome');
</script>
</body>
</html>
