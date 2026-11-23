<?php
require "init.php";
require "includes/header.inc";

function progEdPath() {
  // for Kay's dev env
  if ($_SERVER['HTTP_HOST'] == 'localhost') {
    return "/program-editor";
  }
  return "https://iacr.org/tools/program";
}
?>
<div class="container-fluid float-left">
  <div class="row">
    <div class="col-4 col-lg-3 col-xl-2">
      <?php include "includes/leftnav.inc";?>
    </div>
    <div class="col-8">
      <h3>Program generation</h3>
      <p>
        Once the acceptance decisions are finalized, you should
        create the program for the website using the IACR tool. This form
        will automatically import your list of accepted papers.
      </p>
      <p>
        <strong>Submitting this form will require you to login with your IACR reference number and
        password</strong>.
      </p>
      <?php
      global $Opt;
      if ($Opt['iacrType'] === 'tosc' or $Opt['iacrType'] === 'tches') {
      echo <<<EOF
      <p class="alert alert-warning">
      Note: for FSE/ToSC or CHES/TCHES, the papers for the conference consist of multiple volumes.
      You can import the papers for the other volumes in the program editor using
      the menu item "Import -&gt; Import papers from ToSC and TCHES".
      </p>
EOF;
}
?>

      <form action="<?php echo progEdPath(); ?>/receiveFromHotCRP.php" method="post">
        <button id="submitAccepted" class="button button-primary" type="submit" disabled>Create program</button>
        <input type="hidden" name="name" value="<?php echo $Opt['longName']; ?>" />
        <textarea id="accepted" class="d-none" name="accepted" rows="8" cols="80" readonly></textarea>
      </form>
      <div id="loading" class="d-flex justify-content-center" role="status">
        <div class="spinner-border m-4"></div><span id="loadMessage" class="align-self-center">Loading data...</span>
      </div>
    </div>
  </div>
</div>
<script>
  setActiveMenu('menuprogram');

  $.getJSON('downloadaccepted.php', function(data) {
    console.dir(data);
    $('#accepted').html(JSON.stringify(data, null, 2));
    let submitButton = document.getElementById('submitAccepted');
    if (submitButton) {
      submitButton.removeAttribute('disabled');
    }
    document.getElementById('loading').remove();
  })
  .fail(function(jqxhr, textStatus, error) {
    $('#loadMessage').text(textStatus);
    console.dir(jqxhr);
  });
</script>
</body>
</html>
