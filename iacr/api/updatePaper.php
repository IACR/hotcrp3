<?php
require 'lib.php';
// This allows the submit server to update a paper to record
// the final version was uploaded, or the slides were uploaded,
// or a video was uploaded.
global $Opt;

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
    $dsn = 'mysql:host=localhost;dbname=' . $Opt['dbName'];
    $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC];
    $db = new PDO($dsn, $Opt['dbUser'], $Opt['dbPassword'], $options);
    $sql = "UPDATE Paper set timeFinalSubmitted=? WHERE paperId=?";
    $stmt = $db->prepare($sql);
    $stmt->execute([time(), $_POST['paperId']]);
    $sql = "DELETE FROM PaperOption WHERE paperId=? AND optionId=?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$_POST['paperId'], 3333]);
    $sql = "INSERT INTO PaperOption (paperId,optionId,value) VALUES (?,?,1)";
    $stmt = $db->prepare($sql);
    $stmt->execute([$_POST['paperId'], 3333]);
    echo json_encode(array("response" => "ok"));
} catch (PDOException $e) {
  showError('Database error: ' . $e->message());
}
?>
