<?php

require __DIR__ ."/../../conf/options.php";

// The iacr_paperid should be globally unique across all hotcrp instances. It should be short,
// because it is hashed to create the DOI for the paper. It should not be parsed to extract
// other elements like the volume number. Note that publish.iacr.org depends upon the fact
// that only characters '-', a-z, A-Z, and 0-9 should be used.

function iacr_paperid($paperId) {
  global $Opt;
  $journal_suffix = strval($Opt['volume']) . '-' . strval($Opt['issue']) . '-' . strval($paperId);
  $conf_suffix = strval($Opt['year']) . '-' . strval($paperId);
  $paper_id = '';
  switch ($Opt['iacrType']) {
    case 'cic':
      $paper_id = 'cc' . $journal_suffix;
      break;
    case 'tches':
      $paper_id = 'tc' . $journal_suffix;
      break;
    case 'tosc':
      $paper_id = 'to' . $journal_suffix;
      break;
    case 'crypto':
      $paper_id = 'cr' . $conf_suffix;
      break;
    case 'eurocrypt':
      $paper_id = 'eu' . $conf_suffix;
      break;
    case 'asiacrypt':
      $paper_id = 'as' . $conf_suffix;
      break;
    case 'tcc':
      $paper_id = 'tc' . $conf_suffix;
      break;
    case 'pkc':
      $paper_id = 'pk' . $conf_suffix;
      break;
    case 'rwc':
      $paper_id = 'rw' . $conf_suffix;
      break;
    default: // rump falls into this category.
      $paper_id = 'un' . $Opt['dbName'] . strval($paperId);
      break;
  }
  if (preg_match('/^[-a-zA-Z0-9]+$/', $paper_id, $match) === 0) {
    error_log("Bad paper_id: $paper_id");
    throw new Exception("Bad paper_id: $paper_id");
  }
  return $paper_id;
}

require_once('/var/www/util/hotcrp/hmac.php');
/**
 * $optionId is one of IACR_FINAL_ID, IACR_SLIDES_ID, or IACR_VIDEO_ID
 * $paperId is the ID of the paper that this appears on.
 * $email is the email of the user.
*/
function get_iacr_url($optionId, $paperId) {
  global $Opt;
  $email = Contact::$main_user->email;
  $iacrType = $Opt['iacrType'];
  $dbName = $Opt['dbName'];
  $paper_msg = get_paper_message($iacrType,
                                 $Opt['year'],
                                 $paperId,
                                 $email,
                                 'hc',
                                 $dbName);
  $querydata = array('venue' => $iacrType,
                     'year' => $Opt['year'],
                     'paperId' => $paperId,
                     'email' => $email,
                     'shortName' => $dbName,
                     'auth' => get_hmac($paper_msg),
                     'app' => 'hc');
  switch($optionId) {
    case PaperOption::IACR_FINAL_ID:
      if ($iacrType !== 'cic' && $iacrType !== 'tosc') {
        return 'https://iacr.org/submit/upload/paper.php?' . http_build_query($querydata);
      } else { // 'cic' and 'tosc' go to publish.iacr.org instead.
        // we need acceptance date and submission date.
        try {
          $db = new PDO("mysql:host=localhost;dbname=$dbName;charset=utf8",
                        $Opt['dbUser'],
                        $Opt['dbPassword']);
          $sql = "SELECT value FROM PaperOption where paperId=:paperId and optionId=:optionId";
          $stmt = $db->prepare($sql);
          $res = $stmt->bindParam(':paperId', $paperId, PDO::PARAM_INT);
          $pubtype_id = PaperOption::IACR_PUBTYPE_ID;
          $res = $stmt->bindParam(':optionId', $pubtype_id, PDO::PARAM_INT);
          $res = $stmt->execute();
          $row = $stmt->fetch(PDO::FETCH_ASSOC);
          $pubtype = $row['value'];
          error_log('pubtype was ' . $pubtype);
          // These match what create_conf.py will create in the PaperOption table and the enum values
          // publish.iacr.org expects.
          $pubtype_values = array(1 => 'RESEARCH',
                                  2 => 'SOK',
                                  3 => 'ERRATA');
          $pubtype = $pubtype_values[$pubtype];
          $sql = "SELECT timeSubmitted FROM Paper WHERE paperId=:paperId";
          $stmt = $db->prepare($sql);
          $res = $stmt->bindParam(':paperId', $paperId, PDO::PARAM_INT);
          $res = $stmt->execute();
          $row = $stmt->fetch(PDO::FETCH_ASSOC);
          $timeSubmitted = $row['timeSubmitted'];
          $submitted = date('Y-m-d H:m:s', $timeSubmitted);
          // This is an approximation to acceptance timestamp. The actual acceptance date
          // is apparently only stored in the ActionLog as an ill-defined action. We
          // simply look for the last acceptance decision on ANY paper.
          $stmt = $db->prepare('SELECT max(timestamp) AS accepted FROM ActionLog WHERE action LIKE "Set decision:%" AND action LIKE "%ccept%"');
          $stmt->execute();
          $timeAccepted = $stmt->fetch(PDO::FETCH_ASSOC)['accepted'];
          if (!$timeAccepted) {
            $timeAccepted = Conf::$now;
          }
          $accepted = date('Y-m-d H:m:s', $timeAccepted);
          $stmt = null;
          $db = null;
          $iacr_paperid = iacr_paperid($paperId);
          $authmsg = $iacr_paperid . $Opt['shortName'] . $paperId . 'candidate' . $submitted . $accepted;
          $authmsg = $authmsg . $iacrType . $Opt['volume'] . $Opt['issue'] . $pubtype;
          $auth = hash_hmac('sha256', $authmsg, $Opt['publish_shared_key']);
          $querydata = array('paperid' => $iacr_paperid,
                             'auth' => $auth,
                             'issue' => $Opt['issue'],
                             'volume' => $Opt['volume'],
                             'version' => 'candidate',
                             'submitted' => $submitted,
                             'accepted' => $accepted,
                             'email' => $email,
                             'hotcrp' => $Opt['shortName'],
                             'hotcrp_id' => $paperId,
                             'journal' => $iacrType,
                             'pubtype' => $pubtype);
          if (str_starts_with($Opt['shortName'], 'fake')) {
            return 'https://publishtest.iacr.org/submit?' . http_build_query($querydata);
          }
          return 'https://publish.iacr.org/submit?' . http_build_query($querydata);
        } catch (PDOException $e) {
          $submitted = 'error';
          $accepted = 'error';
          error_log('unable to fetch accepted and submitted: ' . $e->message());
          return NULL;
        }
      }
      break;
    case PaperOption::IACR_SLIDES_ID:
      return 'https://iacr.org/submit/upload/slides.php?' . http_build_query($querydata);
    case PaperOption::IACR_VIDEO_ID:
      return 'https://iacr.org/submit/upload/video.php?' . http_build_query($querydata);
    case PaperOption::IACR_COPYRIGHT_ID:
      return "/$dbName/iacrcopyright/" . strval($paperId);
  }
  error_log('An error occurred in get_iacr_url');
  return NULL;
}
?>
