<?php
require "init.php";
include "includes/header.inc";
global $Opt;
require_once "/var/www/util/hotcrp/hmac.php";
// The download URL for the zip archive of final papers.
$url = "https://iacr.org/submit/api/?action=download&venue=" . $Opt['iacrType'] . "&shortName=" . $Opt['dbName'] . "&year=" . $Opt['year'];
$url .= "&auth=" . get_hmac(get_conf_message($Opt['dbName'], $Opt['iacrType'], $Opt['year']));
$dbName = $Opt['dbName'];
?>
<div class="container-fluid float-left">
  <div class="row">
    <div class="col-4 col-lg-3 col-xl-2">
      <?php include "includes/leftnav.inc";?>
    </div>
    <div class="col-8">
      <h3>Final papers</h3>
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
        <a href="../search?q=&t=acc#view">searching for accepted papers</a>
        and selecting "View options" to see how many have uploaded their final versions.
      </p>
      <p>
        You can
        <a style="font-weight: 600;" target="_blank"
        <?php echo "href=\"$url\" download=\"$dbName.zip\"";?>>Download a Zip archive</a>  of all final versions of papers. (warning: this is slow).
      </p>
      </p>
    </div>
  </div>
</div>
<script>
 setActiveMenu('menufinal');
</script>
</body>
</html>
