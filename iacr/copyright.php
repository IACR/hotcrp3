<?php
require "init.php";
require "/var/www/util/hotcrp/copyright_db.php";
include "includes/header.inc";
try {
  $db = new PDO("mysql:host=localhost;dbname=$dbname;charset=utf8", $dbuser, $dbpassword);
  // outcome>0 and timeWithdrawn = 0 corresponds to an accepted paper.
  $sql = "select paperId,title FROM copyright WHERE shortName=:dbName";
  $stmt = $db->prepare($sql);
  $stmt->bindParam(':dbName', $Opt['dbName']);
  if (!$stmt->execute()) {
    echo 'Unable to execute query';
    exit();
  }
  $copyrights = $stmt->fetchAll(PDO::FETCH_ASSOC);
  $db = null;
} catch (PDOException $e) {
  echo $e->getMessage();
}
?>
<div class="container-fluid float-left">
  <div class="row">
    <div class="col-4 col-lg-3 col-xl-2">
      <?php include "includes/leftnav.inc";?>
    </div>
    <div class="col-8">
      <h3>Submitted copyright forms</h3>
      <p>
        Once you open the papers for final submission, the authors will see an
        IACR copyright form to be filled out. The papers listed below have had
        copyright submitted, but you should check which ones do <strong>not</strong>
        have copyright by
        <a href="../search?q=&t=acc#view">searching for accepted papers</a>
        and checking "IACR Copyright Agreement" in the options.
      </p>
      <ol>
      <?php foreach($copyrights as $paper) {
        echo '<li>' . $paper['title'] . '</li>';
      }
      ?>
      </ol>
    </div>
  </div>
</div>
<script>
 setActiveMenu('menucopyright');
</script>
</body>
</html>
<?php
