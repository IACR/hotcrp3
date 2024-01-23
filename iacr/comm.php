<?php
require "init.php";
include "includes/header.inc";
?>

<div class="container-fluid float-left">
  <div class="row">
    <div class="col-4 col-lg-3 col-xl-2">
      <?php include "includes/leftnav.inc";?>
    </div>
    <div class="col-8">
      <h3>Import program committee</h3>
      <p>
        The standard HotCRP user interface provides several
        ways to create accounts for your program committee:
      </p>
      <ul>
        <li>
          <a href="../profile/new#bulk">Import the program committee directly from the conference website</a>.
          This requires you to fill in their email addresses, but it improves the accuracy
          of identifying co-authors in the next step.
        <?php include "committee.php";?>
        <script>
        // This hides some things from hotcrp that we don't want.
        let nodes = document.querySelectorAll('div.g strong');
        nodes.forEach(function(node, currentIndex, listObj) {
          node.style.display = 'none';
        });
        </script>
        <form action="<?php echo hoturl_post('../profile/new', join('&amp;', array('u=new')) . '#bulk');?>" method="POST">
        <textarea class="w-100" name="bulkentry" id="pclist" placeholder="Select a conference above"></textarea>
        <input type="file" name="bulk" style="display:none">
        <button type="submit" class="my-2 button button-primary" name="savebulk" value="1" id="commSubmit" disabled>Add accounts for program committee</button>
        </form>
        </li>
        <li>
          <a href="../profile/new#bulk">Upload a CSV file containing names, affiliations,
          and email addresses</a>. Unfortunately, this doesn't import CryptoDB ids,
          which help in identifying collaborators automatically.
        </li>
      </ul>
      <p class="alert alert-info">
        <strong>Once you have created the accounts of the program committee, you should <a href="coll.php">import
        their recent collaborators<a>.</strong>
      </p>
      <p>
      <strong>NOTE:</strong> if you have a PC list in a previous instance of HotCRP, you can download it from the "Users" view as an administrator.
      </p>
      <ol>
      <li>Logout from this instance and login to the other HotCRP instance as an admin</li>
      <li>Navigate to "Users",</li>
      <li>Select the Program Committee instead of "All users",</li>
      <li>At the bottom of the list you will see "Download". Select "PC Info" and click "Go".</li>
      <li>you should be able to upload that file above if you login here again.</li>
      </ol>
      <div class="d-flex align-items-start">
      <figure class="figure">
      <figcaption class="text-center figure-caption">Navigate to "Users"</figcaption>
      <img class="figure-img img-fluid shadow-sm" src="../images/iacr_users.png">
      </figure>
      <figure class="ml-2 figure">
      <figcaption class="text-center figure-caption">Select "Program committee"</figcaption>
      <img class="figure-img shadow-sm img-fluid" src="../images/iacr_pc.png">
      </figure>
      <figure class="ml-2 figure">
      <figcaption class="text-center figure-caption">Select Download -&gt; PC Info</figcaption>
      <img class="figure-img ml-4 shadow-sm img-fluid" src="../images/iacr_download.png">
      </figure>
      </div>
    </div>
  </div>
</div>
<script>
 setActiveMenu('menucomm');
</script>
</body>
</html>
