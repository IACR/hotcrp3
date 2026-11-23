<?php
// pages/p_iacr_program.php -- send accepted papers to the IACR program editor (/iacr_program)
// Part of the IACR extensions; not part of upstream HotCRP.
// Replaces iacr/program.php.

class IACR_Program_Page {
    /** @return string */
    static function program_editor_url(Qrequest $qreq) {
        // for Kay's dev env
        if ($qreq->navigation()->host === "localhost") {
            return "/program-editor";
        }
        return "https://iacr.org/tools/program";
    }

    static function go(Contact $user, Qrequest $qreq) {
        IACR_Page::check_chair($user, $qreq);
        $conf = $user->conf;
        $type = $conf->opt("iacrType");
        IACR_Page::print_header($qreq, "IACR functionality of HotCRP", "program");
        echo '<h3>Program generation</h3>
<p>
  Once the acceptance decisions are finalized, you should
  create the program for the website using the IACR tool. This form
  will automatically import your list of accepted papers.
</p>
<p>
  <strong>Submitting this form will require you to login with your IACR reference number and
  password</strong>.
</p>
';
        if ($type === "tosc" || $type === "tches") {
            echo '<p class="alert alert-warning">
Note: for FSE/ToSC or CHES/TCHES, the papers for the conference consist of multiple volumes.
You can import the papers for the other volumes in the program editor using
the menu item "Import -&gt; Import papers from ToSC and TCHES".
</p>
';
        }
        echo '<form action="', htmlspecialchars(self::program_editor_url($qreq)), '/receiveFromHotCRP.php" method="post">
  <button id="submitAccepted" class="button button-primary" type="submit" disabled>Create program</button>
  <input type="hidden" name="name" value="', htmlspecialchars($conf->opt("longName") ?? ""), '" />
  <textarea id="accepted" class="d-none" name="accepted" rows="8" cols="80" readonly></textarea>
</form>
<div id="loading" class="d-flex justify-content-center" role="status">
  <div class="spinner-border m-4"></div><span id="loadMessage" class="align-self-center">Loading data...</span>
</div>
<script>
  $.getJSON(', json_encode($conf->hoturl("iacr_accepted_json")), ', function(data) {
    $("#accepted").val(JSON.stringify(data, null, 2));
    let submitButton = document.getElementById("submitAccepted");
    if (submitButton) {
      submitButton.removeAttribute("disabled");
    }
    document.getElementById("loading").remove();
  })
  .fail(function(jqxhr, textStatus, error) {
    $("#loadMessage").text((jqxhr.responseJSON && jqxhr.responseJSON.error) || textStatus);
  });
</script>
';
        IACR_Page::print_footer();
    }
}
