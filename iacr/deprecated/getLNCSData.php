<?php
require "finalLib.php";
global $Opt, $Me;
header("Content-Type: application/json");
if (!$Me->is_signed_in() || !$Me->privChair) {
   echo json_encode(array('error' => 'unable to authenticate'));
   exit();
}
$filename = getLNCSFilename();
$volumes = file_get_contents($filename);
if ($volumes !== FALSE) {
  echo $volumes;
  exit();
}

$paperdata = getFinalPaperData();
$data = array('venue' => $Opt['iacrType'],
              'year' => $Opt['year'],
              'unassigned_papers' => $paperdata,
              'volumes' => array());
              // We rewrite it in a different format with volumes.
$jstr = json_encode($data, JSON_PRETTY_PRINT);
if (file_put_contents($filename, $jstr) === FALSE) {
   echo json_encode(array('error' => 'unable to save'));
}

echo $jstr;

?>
