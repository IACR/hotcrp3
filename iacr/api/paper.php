<?php
require 'lib.php';

// This allows the submit server to retrieve all of the
// metadata about a single paper, including title, abstract,
// author information, etc. It does not support pulling
// any PDFs.
global $Opt;

header('Content-Type: application/json');
if ($Opt['iacrType'] !== $_GET['venue'] ||
    strval($Opt['year']) !== $_GET['year'] ||
    isAuthenticated($_GET) !== TRUE) {
  showError('wrong year:' . $Opt['year']);
  exit;
}
  
try {
  $dbname = $Opt['dbName'];
  $db = new PDO("mysql:host=localhost;dbname=$dbname;charset=utf8", $Opt['dbUser'], $Opt['dbPassword']);
  $sql = 'SELECT title,authorInformation,abstract FROM Paper WHERE outcome > 0 AND timeWithdrawn = 0 and paperId=:paperId';
  $stmt = $db->prepare($sql);
  $stmt->bindParam(':paperId', $_GET['paperId']);
  if (!$stmt->execute()) {
    showError('Unable to execute');
    $db = null;
    exit;
  }

  $paper = $stmt->fetch(PDO::FETCH_ASSOC);
  $authorInfo = preg_split("/[\n]/", $paper["authorInformation"], -1, PREG_SPLIT_NO_EMPTY);
  $paper["authors"] = array();
  $paper["affiliations"] = array();
  $paper["authorlist"] = array();
  // concats first and last names and adds to authors array + populates affiliations array
  foreach ($authorInfo as $authorLine) {
    // this is a HotCRP thing
    $author = Author::make_tabbed($authorLine);
    $name = $author->firstName . " " . $author->lastName;
    $paper["authorlist"][] = array("name" => $name,
                                   "lastName" => $author->lastName,
                                   "email" => $author->email,
                                   "affiliation" => $author->affiliation);
    $paper["authors"][] = $name;
    $paper["affiliations"][] = $author->affiliation;
  }
  unset($paper["authorInformation"]);
  echo json_encode($paper, JSON_PRETTY_PRINT);
  $db = null;
} catch (PDOException $e) {
  showError('Database error: ' . $e->message());
}
?>
