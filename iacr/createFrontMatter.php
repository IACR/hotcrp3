<?php
require "init.php";
require_once "finalLib.php";
include "ellipsize.php";

// This echos the LaTeX for front matter of an LNCS proceedings.
function echoPaper($paper) {
  $authors = $paper['authorlist'][0]['name'];
  $institute = $paper['authorlist'][0]['affiliation'];
  $numauthors = count($paper['authorlist']);
  for ($i = 1; $i < $numauthors - 1; $i++) {
    $authors .= ', ' . $paper['authorlist'][$i]['name'];
    $institute .= ', ' . $paper['authorlist'][$i]['affiliation'];
  }
  if ($numauthors > 1) {
    $authors .= ' and ' . $paper['authorlist'][$numauthors - 1]['name'];
    $institute .= ' and ' . $paper['authorlist'][$numauthors - 1]['affiliation'];
  }
  foreach($paper['authorlist'] as $author) {
    echo "% author: " . $author['name'] . ', ' . $author['affiliation'] . "\n";
  }
  if (!empty($paper['pages'])) {
    echo "% This paper has " . $paper['pages'] . " pages in the PDF\n";
  }
  $institute = str_replace('&', "\\&", $institute);
  echo "\\author{" . $authors . "}\n";
  echo "% \\institute{" . $institute . "}\n";
  echo "\\title{" . $paper['title'] . "}\n";
  echo "\\maketitle\n";
  if (!empty($paper['pages'])) {
    return $paper['pages'];
  }
  return 0;
}

global $Opt;
$dbname = $Opt['dbName'];

// First retrieve all data from database, and parse the lncseditor file.
try {
  $db = new PDO("mysql:host=localhost;dbname=$dbname;charset=utf8", $Opt['dbUser'], $Opt['dbPassword']);
  // roles=5 means program chair.
  $sql = "select firstName,lastName,affiliation from ContactInfo where roles=5 order by lastName";
  $stmt = $db->query($sql);
  $chairs = $stmt->fetchAll(PDO::FETCH_ASSOC);
  // roles=1 means program committee
  $sql = "select firstName,lastName,affiliation,country from ContactInfo where roles=1 order by lastName";
  $stmt = $db->query($sql);
  $committee = $stmt->fetchAll(PDO::FETCH_ASSOC);
  // Now fetch people who did external reviews.
  $sql = "select DISTINCT firstName,lastName,affiliation from ContactInfo where roles=0 and contactId in (select contactId from PaperReview where reviewType=1) order by lastName";
  $stmt = $db->query($sql);
  $externalReviewers = $stmt->fetchAll(PDO::FETCH_ASSOC);
  $db = null;
} catch (PDOException $e) {
  http_response_code(500);
  echo $e->message();
  exit();
}
$lncsData = file_get_contents(getLNCSFilename());
if ($lncsData === FALSE) {
  http_response_code(500);
  exit;
}
$lncsData = json_decode($lncsData, TRUE);
if ($lncsData === NULL) {
  http_response_code(500);
  exit;
}
// Now we have $chairs, $committee, $externalReviwers, and $lncsData.

header('Content-Type: text/plain');
echo file_get_contents('includes/frontmatter1.txt');
foreach($chairs as $chair) {
  echo $chair['firstName'] . ' ' . $chair['lastName'] . ' & ' . str_replace('&', "\\&", $chair['affiliation']) . "\\\\\n";
}
echo <<<EOT
\\end{longtable}\n
\\section*{Steering Committee}
\\begin{longtable}{p{0.35\\textwidth}p{0.65\\textwidth}}
INSERT NAME & INSERT AFFILIATION\\\\
\\end{longtable}
\\section*{Program Committee}
\\begin{longtable}{p{0.35\\textwidth}p{0.65\\textwidth}}
EOT;
foreach ($committee as $member) {
  $line = $member['firstName'] . ' ' . $member['lastName'] . ' & ' . str_replace('&', "\\&", $member['affiliation']);
  if (isset($member['country'])) {
     $line = $line . ", " . $member['country'];
   }
  echo $line  . "\\\\\n";
}
echo <<<EOF
\\end{longtable}

\\section*{Additional Reviewers}
\\begin{multicols}{2}
\\noindent 
EOF;
// Now generate the list of external reviewers. Affiliations will go in comments.
foreach ($externalReviewers as $member) {
  if (!empty($member['firstName']) && !empty($member['lastName'])) {
    echo $member['firstName']  . ' ' . $member['lastName'] . "\\\\\n";
  }
}
echo "\\end{multicols}\n\n";
echo "% alternate version with affiliations (not requested by Springer)\n";
echo "% \\begin{longtable}{p{0.35\\textwidth}p{0.65\\textwidth}}\n";
foreach ($externalReviewers as $member) {
  echo "% " . $member['firstName'] . ' ' . $member['lastName'] . ' & ' . str_replace('&', "\\&", $member['affiliation']) . "\\\\\n";
}
echo " %\\end{longtable}\n";

// Output table of contents. We take it from the editor.
echo "\\tableofcontents\n\n\\mainmatter\n\n";
$volumeno = 1;
$pageno = 1;
echo "\\setcounter{page}{1}\n";
foreach($lncsData['volumes'] as $volume) {
  foreach($volume['topics'] as $topic) {
    echo "\\addtocontents{toc}{\\protect\\section*{";
    echo $topic['name'] . "}}\n";
    foreach ($topic['papers'] as $paper) {
      $pages = echoPaper($paper);
      $pageno += $pages;
      echo "\\setcounter{page}{" . $pageno . "}\n";
    }
  }
}
if (count($lncsData['unassigned_papers']) > 0) {
  echo "\\addtocontents{toc}{\\protect\\section*{Uncategorized papers}}\n";
  foreach($lncsData['unassigned_papers'] as $paper) {
    $pages = echoPaper($paper);
    $pageno += $pages;
    echo "\\setcounter{page}{" . $pageno . "}\n";
  }
}
echo <<<EOD
\\phantom{Author Index}
\\addcontentsline{toc}{title}{\\protect\\textbf{Author Index}}
\\clearpage
\\end{document}
EOD;
?>
