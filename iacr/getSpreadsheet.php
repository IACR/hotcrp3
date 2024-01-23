<?php
function cleanData(&$str) {
  $str = preg_replace("/\t/", "\\t", $str);
  $str = preg_replace("/\r?\n/", "\\n", $str);
}

require "init.php";
global $Opt;
$dbname = $Opt['dbName'];

try {
  $db = new PDO("mysql:host=localhost;dbname=$dbname;charset=utf8", $Opt['dbUser'], $Opt['dbPassword']);
  // NOTE: this is based on what appeared in src/listactions/la_getauthors.php. The clause conflictType >= 32
  // is apparently undocumented, but depends upon the fact that CONFLICT_AUTHOR is 32. The conflictType is a bitmask of conflicts.
  $sql = 'SELECT Paper.paperId,email,firstName,lastName,title FROM Paper,PaperConflict,ContactInfo WHERE Paper.paperId=PaperConflict.paperId AND conflictType >= 32 AND timeWithdrawn = 0 AND outcome>0 AND PaperConflict.contactId=ContactInfo.contactId ORDER BY Paper.paperId';
  $stmt = $db->query($sql);
  $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
  header("Content-Disposition: attachment; filename=\"author_emails.tsv\"");
  header("Content-Type: application/vnd.ms-excel");
  echo "paperId\tEmail\tGiven name\tFamily name\tTitle\r\n";
  foreach($contacts as $row) {
    echo implode("\t", array_values($row)) . "\r\n";
  }
  $db = null;
} catch (PDOException $e) {
  echo $e->message();
}
?>
