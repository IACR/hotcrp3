<?php
  require "init.php";
  require "finalLib.php";
  
  global $Opt;
  $dbname = $Opt['dbName'];

function dedup_strings($arr) {
  $deduped = array();
  foreach($arr as $key => $val) {
    $deduped[$val] = true;
  }
  return array_keys($deduped);
}

try {
    $db = new PDO("mysql:host=localhost;dbname=$dbname;charset=utf8", $Opt['dbUser'], $Opt['dbPassword']);
    $sql = "SELECT paperId,title,authorInformation,abstract FROM Paper WHERE outcome > 0 AND timeWithdrawn = 0";
    $stmt = $db->query($sql);
    $papers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($Opt['iacrType'] == 'tches' || $Opt['iacrType'] == 'tosc' || $Opt['iacrType'] == 'cic') {
      foreach($finalData as &$paper) {
        $paper['paperId'] = $dbname . '_' . $paper;
      }
    }

    // First fetch final papers.
    $finalData = getFinalPaperData();
    $finalPapers = array();
    // Create finalPapers version of $finalData, keyed by paperId for easy lookup.
    foreach($finalData as &$paper) {
      if ($Opt['iacrType'] == 'tches' || $Opt['iacrType'] == 'tosc' || $Opt['iacrType'] == 'cic') {
        $paper['paperId'] = $dbname . '_' . $paper;
      }
      $finalPapers[$paper['paperId']] = $paper;
    }
    foreach ($papers as &$paper) {
      $authorInfo = preg_split("/[\n]/", $paper['authorInformation'], -1, PREG_SPLIT_NO_EMPTY);
      $paper['authors'] = array();
      $paper['affiliations'] = array();

      // concats first and last names and adds to authors array + populates affiliations array
      foreach ($authorInfo as $authorLine) {
        // this is a HotCRP thing
        $author = Author::make_tabbed($authorLine);

        $paper['authors'][] = $author->firstName . ' ' . $author->lastName;
        $paper['affiliations'][] = $author->affiliation;
      }
      unset($paper['authorInformation']);
      $paper['affiliations'] = dedup_strings($paper['affiliations']);
      if (isset($finalPapers[$paper['paperId']])) {
        $finalPaper = $finalPapers[$paper['paperId']];
        $paper['title'] = $finalPaper['title'];
        $paper['abstract'] = $finalPaper['abstract'];
        if (isset($finalPaper['pubkey'])) {
          $paper['pubkey'] = $finalPaper['pubkey'];
        }
        if (isset($finalPaper['topics'])) {
          $paper['keywords'] = implode(', ', $finalPaper['topics']);
        }
        if (isset($finalPaper['pages'])) {
          $paper['pages'] = $finalPaper['pages'];
        }
      }
    }

    unset($paper);
    header('Content-Type: application/json');
    $data = array('_source' => 'IACR/hotcrp v1',
                  'acceptedPapers' => $papers);
    echo json_encode($data, JSON_PRETTY_PRINT);
    $db = null;
  } catch (PDOException $e) {
    echo $e->message();
  }
?>
