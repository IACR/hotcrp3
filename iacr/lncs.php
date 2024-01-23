<?php
require_once "init.php";
require_once "includes/header.inc";
global $Opt, $Conf;
require_once "finalLib.php";
$dbName = $Opt['dbName'];

require_once "/var/www/util/hotcrp/hmac.php";
// The download URL for the zip archive of final papers.
$url = "https://iacr.org/submit/api/?action=download&venue=" . $Opt['iacrType'] . "&shortName=" . $Opt['dbName'] . "&year=" . $Opt['year'];
$url .= "&auth=" . get_hmac(get_conf_message($Opt['dbName'], $Opt['iacrType'], $Opt['year']));
$shortName = $Opt['shortName'];
?>
<div class="container-fluid float-left">
  <div class="row">
    <div class="col-4 col-lg-3 col-xl-2">
      <?php include "includes/leftnav.inc";?>
    </div>
    <div class="col-8">
      <h3>LNCS Preparation</h3>
<?php
try {
  $db = new PDO("mysql:host=localhost;dbname=$dbName;charset=utf8", $Opt['dbUser'], $Opt['dbPassword']);
  // outcome>0 and timeWithdrawn = 0 corresponds to an accepted paper. optionId is from create_conf.py when
  // the conference is first set up. It indicates that a final version was uploaded.
  $optionId = getFinalPaperOptionId();
  if (!$optionId) {
    die('Missing iacrFinalPaperOption');
    exit();
  }
  $sql = "select paperId,title from Paper where outcome>0 and timeWithdrawn = 0 and paperId not in (select paperId from PaperOption where optionId = :optionId)";
  $stmt = $db->prepare($sql);
  $stmt->bindParam('optionId', $optionId);
  $stmt->execute();
  $papers = $stmt->fetchAll(PDO::FETCH_ASSOC);
  if ($papers && count($papers) > 0) {
    echo "<div class='alert alert-warning'><p>Warning: the following accepted papers appear to have not uploaded their final versions yet:</p><ul>";
    foreach($papers as $paper) {
      echo "<li><a href=\"../paper/" . $paper['paperId'] . "\">" . $paper['title'] . "</a></li>";
    }
    echo "</ul></div>";
  } else {
    echo <<<EOI
    <div class='alert alert-success'>All final versions have been uploaded.</div>
      <p>
        Once you have all of the final versions uploaded, you can proceed to create
        the material required by Springer for the LNCS volumes of the proceedings.
        Preparation of the Springer LNCS volumes should follow the instructions
        provided by Springer, which have in the past been located at
        <a href="https://www.springer.com/gp/computer-science/lncs/editor-guidelines-for-springer-proceedings">this URL</a>. Note that IACR uses their own copyright
        forms, so you should ignore the LNCS instructions for Copyright.
      </p>
      <p>
        The instructions for preparation of the LNCS volume(s) requires
        several items:
      </p>
      <ol>
        <li><a style="font-weight: 600;" target="_blank" href="$url" download="$dbName.zip">Download Zip archive</a>  with a bundle of all final versions of papers.</li>
        <li><a style="font-weight: 600" target="_blank" href="getSpreadsheet.php" download="author_emails.tsv">TSV spreadsheet of author emails</a> and disambiguated names of authors (in a spreadsheet TSV)
             
        </li>
        <li><a style="font-weight:600" href="lncsEditor.php">Create the front matter</a> for the LNCS volume(s) (in a LaTeX file).
          This contains the program committee, external reviewers, and table of contents. This requires
         you to group papers into topics and volumes to obey the 500 page limit. For
          this purpose we have created a <a style="font-weight:600" href="lncsEditor.php">drag-and-drop tool</a>
          to simplify the task. If you prefer, you can perform this step manually starting from a
        <a target="_blank" href="getFrontMatter.php" download="frontmatter.tex">single LaTeX file</a>.
        You can also download a <a target="_blank" href="getFinalPaperJson.php" download="final_papers.json">JSON file of metadata for the final papers.</a>
        </li>
      </ol>
EOI;
  }
  $db = null;
} catch (PDOException $e) {
  echo $e->message();
}
?>
</div>
</div>
</div>
<script>
 setActiveMenu('menulncs');
</script>
</body>
</html>
