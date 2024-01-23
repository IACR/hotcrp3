<?php
require_once(dirname(__DIR__)."/conf/options.php");
require_once(dirname(__DIR__)."/src/init.php");
require_once "/var/www/util/hotcrp/hmac.php";
global $Conf;
$Conf = initialize_conf();
initialize_request();

function getFinalPaperOptionId() {
  global $Conf;
  $paper_options = $Conf->options()->universal();
  foreach($paper_options as $id => $papt) {
    if ($papt->iacrSetting == 'final_paper') {
      return $id;
    }
  }
  return NULL;
}

function getLNCSFilename() {
  global $Me;
  return '../filestore/lncs_' . $Me->contactId . '.json';
}

// Fetches the final paper data from www.iacr.org using the API.
// This does not check if all papers have uploaded their final,
// so it may return incomplete data.
function getFinalPaperData() {
  global $Opt;
  $dbname = $Opt['dbName'];
  try {
    $db = new PDO("mysql:host=localhost;dbname=$dbname;charset=utf8", $Opt['dbUser'], $Opt['dbPassword']);
    $sql = "SELECT Paper.paperId,TopicArea.topicName from Paper,PaperTopic,TopicArea WHERE Paper.paperId=PaperTopic.paperId AND PaperTopic.topicId=TopicArea.topicId AND outcome>0 and timeWithdrawn=0";
    $stmt = $db->query($sql);
    $topicRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $topicMap = array();
    foreach($topicRows as $row) {
      if (!array_key_exists($row['paperId'], $topicMap)) {
        $topicMap[$row['paperId']] = array();
      }
      $topicMap[$row['paperId']][] = $row['topicName'];
    }
    $db = null;
  } catch (PDOException $e) {
    return $e->message();
  }

  $url = 'https://www.iacr.org/submit/api/?action=view&venue=' . $Opt['iacrType'] . '&year=' . $Opt['year'] . '&shortName=' . $Opt['dbName'];
  $msg = get_conf_message($Opt['dbName'], $Opt['iacrType'], $Opt['year']);
  $url = $url . '&auth=' . get_hmac($msg);
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($ch, CURLOPT_URL, $url);
  $output = curl_exec($ch);
  curl_close($ch);
  if ($output === FALSE) {
    echo 'Unable to fetch data from www.iacr.org';
    exit;
  }
  $paperdata = json_decode($output, TRUE);
  foreach($paperdata as &$paper) {
    if (array_key_exists($paper['paperId'], $topicMap)) {
      $paper['topics'] = $topicMap[$paper['paperId']];
    }
  }
  return $paperdata;
}

?>
