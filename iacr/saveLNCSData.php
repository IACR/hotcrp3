<?php
require "finalLib.php";
global $Opt, $Me;
header("Content-Type: application/json");
if (!$Me->is_signed_in() || !$Me->privChair) {
  echo json_encode(array('error' => 'unable to authenticate'));
  exit();
}
$filename = getLNCSFilename();
if (empty($_POST['json'])) {
  if (isset($_POST['delete'])) {
    if (file_exists($filename)) {
      if (unlink($filename) === FALSE) {
        echo json_encode(array('error' => 'Unable to delete'));
      } else {
        echo json_encode(array('ok' => 'Data was deleted'));
      }
    } else {
      echo json_encode(array('ok' => 'Data did not exist for this user'));
    }
  } else {
    echo json_encode(array('error' => 'Missing parameter'));
  }
  exit();
}

$data = json_decode($_POST['json'], TRUE);
if (!$data) {
  echo json_encode(array('error' => 'Unable to parse parameter'));
  exit();
}
if (file_put_contents($filename, $_POST['json']) === FALSE) {
  echo json_encode(array('error' => 'Unable to save'));
  exit();
}
echo '{}';

?>
