<?php
require 'lib.php';
require_once '../finalLib.php';
// This allows the submit server to update a paper to record
// the final version was uploaded, or the slides were uploaded,
// or a video was uploaded.
global $Opt, $Conf;

header('Content-Type: application/json');
if (empty($_POST['paperId'])) {
  showError('Missing paperId');
  exit;
}
if (empty($_POST['email'])) {
  showError('Missing email');
  exit;
}     

$msg = get_paper_message($Opt['iacrType'],
                         $Opt['year'],
                         $_POST['paperId'],
                         $_POST['email'],
                         'hc',
                         $Opt['dbName']);

if (!hash_equals(get_hmac($msg), $_POST['auth'])) {
  // 2023-11-30 added ability to authenticate from publish.iacr.org without year.
  $msg = $Opt['shortName'] . $_POST['paperId'] . $_POST['email'];
  if (!hash_equals(get_hmac($msg), $_POST['auth'])) {
    showError('Bad auth token');
    exit;
  }
}

  
if (empty($_POST['action']) || $_POST['action'] !== 'finalPaper') {
  showError('Unknown action');
  exit;
}
try {
  // Note that the ID number is the predefined option value defined in create_conf.py.
  // This used to be hard-coded.
  $optionId = getFinalPaperOptionId();
  if (!$optionId) {
    showError('Unable to update hotcrp: missing option');
  } else {
    $Conf->q("INSERT INTO PaperOption set paperId=?,optionId=?,value=?", $_POST['paperId'], $optionId, 1);
    echo json_encode(array("response" => "ok"));
  }
} catch (PDOException $e) {
  showError('Database error: ' . $e->message());
}
?>
