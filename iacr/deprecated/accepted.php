<?php
require "init.php";
include "includes/header.inc";
global $Opt;
?>
<div class="container-fluid float-left">
  <div class="row">
    <div class="col-4 col-lg-3 col-xl-2">
      <?php include "includes/leftnav.inc";?>
    </div>
    <div class="col-8">
      <h3>Export list of accepted papers</h3>
      <p>
        Once the acceptance decisions are finalized, you should
        list the accepted papers on the website.  The way to do this
        is to download the <tt>papers.json</tt> file and place it in the
        location <tt><?php echo $Opt['conferenceSite'] . '/json/papers.json';?></tt>
      </p>
      <p>
        <a class="button button-primary" target="_blank" href="downloadaccepted.php" download="papers.json">Download papers.json</a>
      </p>
    </div>
  </div>
</div>
<script>
 setActiveMenu('menuaccepted');
</script>
</body>
</html>
