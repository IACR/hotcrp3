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

/**
 * ToSC wants to have both a 'submitted' and 'revised'
 * date for 'Major Revision' papers that were submitted
 * to a previous hotcrp instance. In this case the 'submitted'
 * date is from the first hotcrp, and the 'revised' date is
 * the submission date from the current hotcrp.
 *
 * There are several ways that the date from the previous hotcrp
 * could be provided, but the one we settled upon is to have a
 * PaperOption with ID of PaperOption::IACR_SUBMISSIONURL_ID
 * that contains the previous submission url in the format
 * https://submit.iacr.org/<venue>/paper/<paperid>.
 */

/**
 * This is used in case the PaperOption with id
 * PaperOption::IACR_SUBMISSIONURL_ID exists. Given a venue, we
 * open the database and read the submission date for the paperid.
 */
function get_submission_from_previous_hotcrp($venue, $paperid) {
  // first open the conf/options file
  $conf_file = "/var/www/hotcrp/$venue/conf/options.php";
  error_log("config file is $conf_file\n");
  if (file_exists($conf_file)) {
    $fileContent = file_get_contents($conf_file);
    // extract the database password from the conf/options.php file.
    $pattern = '/\$Opt\["dbPassword"\]\s*=\s*"([^"]+)"/';
    $otherdb = null;
    if (preg_match($pattern, $fileContent, $matches)) {
      // $matches[1] contains the value inside the capture group (the password)
      $password = $matches[1];
      try {
        $otherdb = new PDO("mysql:host=localhost;dbname=$venue;charset=utf8", $venue, $password);
        $sql = 'SELECT timeSubmitted FROM Paper WHERE paperId=:paperId';
        $stmt = $otherdb->prepare($sql);
        $stmt->bindParam(':paperId', $paperid);
        if ($stmt->execute()) {
          $row = $stmt->fetch(PDO::FETCH_ASSOC);
          if ($row) {
            $submitted = date('Y-m-d H:i:s', $row['timeSubmitted']);
            $otherdb = null;
            $stmt = null;
            return $submitted;
          }
          error_log("unable to find timeSubmitted in $venue:$paperid");
        } else {
          error_log("unable to execute for timeSubmitted in $venue:$paperid");
        }
      } catch (Exception $e) {
        error_log("Exception accessing $venue:" . $e->message());
      }
    } else {
      error_log("unable to find password for $venue");
    }
    if ($otherdb) {
      $otherdb = null;
    }
  }
  return '';
}

/**
 * This function returns either an empty string or a date from the previous
 * hotcrp in the format 'YYYY-MM-DD 00:00:00'.
 */
function get_original_submission($db, $paperId) {
  // In the first version, we store the dates in an external file.
  // This method allows us to override what is the PaperOption, and
  // is what we used the first time we handled ToSC.
  $fname = __DIR__ . '/resubmit.php';
  if(file_exists($fname)) {
    require($fname);
    if (isset($original_submit[$paperId])) {
      error_log('got an original_submit ' . $fname);
      return $original_submit[$paperId] . ' 00:00:00';
    }
  } else {
    // Look for a PaperOption of id PaperOption::IACR_SUBMISSIONURL_ID)
    // to see if a URL exists pointing to the original submission.
    $sql = "SELECT data FROM PaperOption where paperId=:paperId and optionId=:optionId";
    $stmt = $db->prepare($sql);
    $res = $stmt->bindParam(':paperId', $paperId, PDO::PARAM_INT);
    $iacr_submission_id = PaperOption::IACR_SUBMISSIONURL_ID;
    $res = $stmt->bindParam(':optionId', $iacr_submission_id, PDO::PARAM_INT);
    $res = $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
      $submission_url = $row['data'];
      error_log('Previous submission url was ' . $submission_url);
      $urlparts = parse_url($submission_url);
      if ($urlparts['host'] === 'submit.iacr.org') {
        $pathparts = explode('/', $urlparts['path']);
        if (count($pathparts) == 4) {
          $venue = $pathparts[1];
          $origpaperid = $pathparts[3];
          return get_submission_from_previous_hotcrp($venue, $origpaperid);
        }
      }
    } else {
      error_log('no submission url with optionid 3888');
    }
  }
  return '';
}

/**
 * Finding the acceptance date in hotcrp is a mess. The acceptance date does not
 * appear in the Paper table. A paper might have been withdrawn, or might never have
 * been finalized for submission. In this function we assume that the paper has been
 * accepted because the current value of Paper.outcome is > 0. The ActionLog table
 * is the only place where a decision is recorded. Those are typically recorded with
 * an action field that looks like 'Decision set:%'. The paperId may or many not be
 * set, depending on where it was a bulk action from a list of papers. If the paperId
 * is not set, then the field might look like
 * Decision set: Accepted (papers 22, 29, 50, 64, 78, 89, 92)
 * or
 * Decision set: Minor revision (papers 3, 10, 39, 45, 59, 62, 65, 81, 82, 84, 85, 90, 122)
 *
 * In the latter case, we would need to know if "Minor revision" is an outcome that counts
 * as an acceptance class (has a value >0). If the program chair has modified the defaults
 * for decision types, then there is a row in Settings table with name='outcome_map' that
 * contains a JSON map in the 'data' column that describes the decision types. An example would
 * be {"1":"Accepted","2":"Minor revision","-3":"Major Revision","-2":"Reject and Resubmit","-1":"Rejected"}
 * This indicates that there are two "accept" classes namely "Accepted" and "Minor revision".
 * Note that some journals (ToSC) treat "Minor revision" as a reject class until they see the
 * revision, while others (CiC) go ahead and assume that this is treated as an acceptance.
 * The only way to tell is to fetch the JSON map and see whether the value is a positive integer.
 * The algorithm we use is to first set the outcome_map to the default, and look in
 * the settings table to see if it should have an override. Then we look in the ActionLog
 * table for any possibly relevant rows. Keep in mind that the decision might be set several
 * times as the chair wavers on a decision. We are looking for the _last_ time that the
 * outcome is changed because that is what we count as the final decision date, so we go
 * through the ActionLog starting with the most recent values until we find a match.
 */
function get_accepted_date($db, $paperId) {
  $stmt = $db->prepare('SELECT data from Settings WHERE name="outcome_map"');
  if ($stmt->execute()) {
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
      $outcome_map = json_decode($row['data'], true);
    } else {
      $outcome_map = array('1' => 'Accepted');
    }
  }
  $outcomes = [];
  foreach ($outcome_map as $value => $name) {
    if (intval($value) > 0) {
      $outcomes[] = $name;
    }
  }
  $alternatives = implode('|', array_map('preg_quote', $outcomes));
  $precise_pattern = '/^Decision set: (' . $alternatives . ')$/i';
  //  error_log('exact pattern of ' . $precise_pattern);
  $list_pattern = '/^Decision set: (' . $alternatives . ') \(papers ([0-9, ]+)\)$/i';
  $stmt = $db->prepare('SELECT timestamp,paperId,action FROM ActionLog WHERE action LIKE "Decision set:%" ORDER BY timestamp DESC');
  $res = $stmt->execute();
  while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    // error_log('action of ' . $row['action'] . '\n');
    if ($row['paperId'] === $paperId && preg_match($precise_pattern, $row['action'])) {
      // error_log('exact matched ' . $row['action']);
      $accepted = date('Y-m-d H:i:s', intval($row['timestamp']));
      return $accepted;
    }
    if (preg_match($list_pattern, $row['action'], $matches)) {
      // error_log('matched multiple ' . $row['action']);
      $raw_numbers = $matches[2]; // e.g., 22, 31, 47
      $papers_array = explode(',', str_replace(' ', '', $raw_numbers));
      if (in_array($paperId, $papers_array)) {
        // error_log('found paper ID ' . strval($paperId));
        $accepted = date('Y-m-d H:i:s', intval($row['timestamp']));
        return $accepted;
      }
    }
  }
  // if we get here then no ActionLog matches. This should not happen
  // but we need an acceptance date so we take the current time.
  $accepted = date('Y-m-d H:i:s');
  error_log('DEFAULT accepted ASSIGNED AS ' . $accepted);
  return $accepted;
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
          if ($row) {
            $pubtype = $row['value'];
          } else {
            $pubtype = 1; // default for RESEARCH.
          }
          // These match what create_conf.py will create in the PaperOption table and the enum values
          // publish.iacr.org expects.
          $pubtype_values = array(1 => 'RESEARCH',
                                  2 => 'SOK',
                                  3 => 'ADDENDUM',
                                  4 => 'CORRIGENDUM',
                                  5 => 'PREFACE',
                                  6 => 'ERRATUM');
          $pubtype = $pubtype_values[$pubtype];
          $sql = "SELECT timeSubmitted FROM Paper WHERE paperId=:paperId";
          $stmt = $db->prepare($sql);
          $res = $stmt->bindParam(':paperId', $paperId, PDO::PARAM_INT);
          $res = $stmt->execute();
          $row = $stmt->fetch(PDO::FETCH_ASSOC);
          $timeSubmitted = $row['timeSubmitted'];
          $submitted = date('Y-m-d H:i:s', $timeSubmitted);
          // now check if it was a major revision.
          $original_submitted = get_original_submission($db, $paperId);
          if (empty($original_submitted)) {
            $revised = '';
          } else {
            $revised = $submitted;
            $submitted = $original_submitted;
          }
          $accepted = get_accepted_date($db, $paperId);
          $stmt = null;
          $db = null;
          $iacr_paperid = iacr_paperid($paperId);
          $authmsg = $iacr_paperid . $Opt['shortName'] . $paperId . 'candidate' . $submitted . $accepted . $revised;
          $authmsg = $authmsg . $iacrType . $Opt['volume'] . $Opt['issue'] . $pubtype;
          $auth = hash_hmac('sha256', $authmsg, $Opt['publish_shared_key']);
          $querydata = array('paperid' => $iacr_paperid,
                             'auth' => $auth,
                             'revised' => $revised,
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
