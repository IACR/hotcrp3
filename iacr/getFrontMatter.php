<?php
  require "../conf/options.php";
  require "../src/init.php";
  require "/var/www/util/hotcrp/hmac.php";
  header('Content-Type: text/plain');
  include "ellipsize.php";
  echo file_get_contents('includes/frontmatter1.txt');
  global $Opt;
  $dbname = $Opt['dbName'];

  try {
    $db = new PDO("mysql:host=localhost;dbname=$dbname;charset=utf8", $Opt['dbUser'], $Opt['dbPassword']);
    // roles=5 means program chair.
    $sql = "select firstName,lastName,affiliation from ContactInfo where roles=5 order by lastName";
    $stmt = $db->query($sql);
    $chairs = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    // roles=1 means program committee
    $sql = "select firstName,lastName,affiliation from ContactInfo where roles=1 order by lastName";
    $stmt = $db->query($sql);
    $committee = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($committee as $member) {
      echo $member['firstName'] . ' ' . $member['lastName'] . ' & ' . str_replace('&', "\\&", $member['affiliation']) . "\\\\\n";
    }
    echo <<<EOF
\\end{longtable}

\\section*{Additional Reviewers}
\\begin{multicols}{2}
\\noindent 
EOF;
    // Now generate the list of external reviewers. Affiliations will go in comments.
    $sql = "select DISTINCT firstName,lastName,affiliation from ContactInfo where roles=0 and contactId in (select contactId from PaperReview where reviewType=1) order by lastName";
    $stmt = $db->query($sql);
    $externalReviewers = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    $db = null;
  } catch (PDOException $e) {
    echo $e->message();
  }
  // Output table of contents. We take it from www.iacr.org
  // instead of HotCRP because final versions are uploaded there.
  $url = 'https://www.iacr.org/submit/api/?action=view&venue=' . $Opt['iacrType'] . '&year=' . $Opt['year'] . '&shortName=' . $Opt['dbName'];
  $msg = get_conf_message($Opt['dbName'], $Opt['iacrType'], $Opt['year']);
  $url = $url . '&auth=' . get_hmac($msg);
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($ch, CURLOPT_URL, $url);
  $output = curl_exec($ch);
  curl_close($ch);
  echo "\\tableofcontents\n\n\\mainmatter\n\n";
  echo "% Create chapters for the table of contents and reorder things.\n";
  echo "\\addtocontents{toc}{\\protect\\section*{Add Topics}}\n";
  $pageno = 1;
  if ($output !== FALSE) {
    $paperdata = json_decode($output, TRUE);
    echo "\\setcounter{page}{1}\n";
    foreach($paperdata as $paper) {
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
      $institute = str_replace('&', "\\&", $institute);
      echo "\\author{" . $authors . "}\n";
      echo "% \\institute{" . $institute . "}\n";
      echo "\\title{" . $paper['title'] . "}\n";
      echo "\\maketitle\n";
      if (!empty($paper['pages'])) {
        $pageno += $paper['pages'];
        echo "% The previous paper had " . $paper['pages'] . " pages in the PDF\n";
        echo "\\setcounter{page}{" . $pageno . "}\n";
      }
    }
  }
echo <<<EOD
\\phantom{Author Index}
\\addcontentsline{toc}{title}{\\protect\\textbf{Author Index}}
\\clearpage
\\end{document}
EOD;

?>
