<?php
require "lib.php";
require "util.php";

global $Opt;

header('Content-Type: application/json');
if (!isset($_GET['auth'])) {
  showError('Unauthenticated request');
  exit;
}
$msg = $Opt['shortName'] . ':' . $Opt['iacrType'];

if (!hash_equals(get_hmac($msg), $_GET['auth'])) {
  showError('Bad auth token');
  exit;
}
  
try {
  $dbname = $Opt['dbName'];
  $db = new PDO("mysql:host=localhost;dbname=$dbname;charset=utf8", $Opt['dbUser'], $Opt['dbPassword']);
  $sql = "SELECT paperId,title,authorInformation,abstract FROM Paper WHERE outcome > 0 AND timeWithdrawn = 0";
  $stmt = $db->query($sql);
  $papers = $stmt->fetchAll(PDO::FETCH_ASSOC);

  foreach ($papers as &$paper) {
    $authorInfo = preg_split("/[\n]/", $paper['authorInformation'], -1, PREG_SPLIT_NO_EMPTY);
    $paper['authors'] = array();
    $paper['affiliations'] = array();
    $paper['authorlist'] = array();
    // concats first and last names and adds to authors array + populates affiliations array
    foreach ($authorInfo as $authorLine) {
      // this is a HotCRP thing
      $author = Author::make_tabbed($authorLine);
      $name = $author->firstName . ' ' . $author->lastName;
      $paper['authorlist'][] = array('name' => $name,
                                     'lastName' => $author->lastName,
                                     'affiliation' => $author->affiliation);
      $paper['authors'][] = $name;
      $paper['affiliations'][] = $author->affiliation;
    }
    if ($Opt['iacrType'] == 'cic') {
      $paper['paperid'] = iacr_paperid($paper['paperId']);
    }
    unset($paper['authorInformation']);
  }

  unset($paper);
  $data = array('_source' => 'IACR/hotcrp v2',
                'shortName' => $Opt['shortName'],
                'longName' => $Opt['longName'],
                'venue' => $Opt['iacrType'],
                'year' => $Opt['year'],
                'acceptedPapers' => $papers);
  if (array_key_exists('volume', $Opt)) {
    $data['volume'] = strval($Opt['volume']);
  }
  if (array_key_exists('issue', $Opt)) {
    $data['issue'] = strval($Opt['issue']);
  }
  echo json_encode($data, JSON_PRETTY_PRINT);
  $db = null;
} catch (PDOException $e) {
  echo $e->message();
}
?>
