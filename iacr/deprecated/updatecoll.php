<?php
require "init.php";
include "includes/header.inc";
?>
<div class="container-fluid float-left">
  <div class="row">
    <div class="col-3">
      <?php include "includes/leftnav.inc";?>
    </div>
    <div class="col-9">
      <h3>Collaborator update results</h3>
<?php
global $Opt;
$dbname = $Opt['dbName'];
try {
  $db = new PDO("mysql:host=localhost;dbname=$dbname;charset=utf8", $Opt['dbUser'], $Opt['dbPassword']);
  $sql = "UPDATE ContactInfo SET collaborators=:coll WHERE contactId=:id";
  $stmt = $db->prepare($sql);
  $counter = 0;
  foreach($_POST as $key => $value) {
    if (preg_match('/^coll-(\d+)$/', $key, $matches)) {
      $counter = $counter + 1;
      $stmt->bindParam(':id', $matches[1]);
      $stmt->bindParam(':coll', $value);
      if (!$stmt->execute()) {
        echo '<div class="alert alert-danger">Error on one row</div>';
      }
    }
  }
  if ($counter) {
    echo "<div class='alert alert-info'>Updated $counter people</alert>";
  }
  $stmt = null;
  $db = null;
} catch(PDOException $e) {
  if ($db != null) {
    $db = null;
  }
  echo $e->getMessage();
}
?>

    </div>
  </div>
</div>
</body>
</html>
