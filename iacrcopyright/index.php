<?php
require_once(dirname(__DIR__)."/conf/options.php");
require_once(dirname(__DIR__)."/src/init.php");
initialize_conf();
initialize_request();
require_once("src/paperrequest.php");

function showError($msg) {
  echo "<html><body><h3>" . $msg . "</h3></body></html>";
}

global $Me, $Qreq, $Opt, $Conf;
if (!$Me || !$Me->is_signed_in()) {
   header("Location: ..");
}

function getCopyrightOptionId() {
  return PaperOption::IACR_COPYRIGHT_ID;
}

// Retrieve the paper row corresponding to the ID in the URL.
// This is a bit of hotcrp magic involving the last part of the URL.
// It validates that the paper by that ID exists, and that the author
// is authorized to edit the paper.
//if (!($prow = PaperTable::fetch_paper_request($Qreq, $Me))) {
//    showError("Error retrieving paper");
//    exit;
//}
try {
  $pr = new PaperRequest($Qreq, false);
  $prow = $pr->prow;
} catch (Redirection $redir) {
  assert(PaperRequest::simple_qreq($this->qreq));
  $Conf->redirect($redir->url);
} catch (PermissionProblem $perm) {
  print 'An error has occurred - are you sure you have permission?';
  exit;
}

require_once("/var/www/util/hotcrp/copyright_db.php");
try {
  $db = new PDO("mysql:host=localhost;dbname=$dbname;charset=utf8", $dbuser, $dbpassword);
  $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);

  // Check to see if the copyright was already signed.
  $stmt = $db->prepare("SELECT COUNT(*) FROM copyright WHERE paperId=:paperId AND shortName=:shortName");
  if (!$stmt) {
    showError("Unable to execute sql for fetching copyright form");
    $db = null;
    exit;
  }
  if (!$stmt->bindParam(":paperId", $prow->paperId, PDO::PARAM_INT) ||
      !$stmt->bindParam(":shortName", $Opt["shortName"], PDO::PARAM_STR)) {
    showError("Unable to bind parameters");
    $db = null;
    exit;
  }
  if (!$stmt->execute()) {
    showError("Unable to find the copyright agreement: " . $db->errorInfo()[0]);
    $db = null;
    exit;
  }
  if ($stmt->fetchColumn() > 0) {
    showError("You already signed the copyright agreement");
    $stmt = null;
    $db = null;
    return;
  }
  $stmt = null;
  // leave $db open
} catch (PDOException $e) {
  showError("Database error: " . $e->getMessage());
  exit;
}
// Create the POST URL
$form_params = array();
$form_params[] = "c=update";
// This URL contains a token to prevent XSRF. It's HotCRP magic.
$action = $Conf->hoturl("iacrcopyright/" . $prow->paperId . "/submit", join("&amp;", $form_params), $Conf::HOTURL_POST);
// Trivial template substitution is used on the copyright forms.
$substitutions = array('[$action]' => $action,
   '[$title]' => $prow->title,
   '[$confName]' => $Opt["longName"],
   '[$authors]' => $prow->authorInformation,
   '[$date]' => date('F j, Y'),
   '[$year]' => date('Y'),
   '[$subId]' => $prow->paperId,
   '[$correspondingAuthor]' => $Me->firstName . ' ' . $Me->lastName
   );
// We use one of two different copyright forms.
switch($Opt["iacrType"]) {
  case "tosc":
    $substitutions['[$longName]'] = "IACR Transactions on Symmetric Cryptology";
    $substitutions['[$shortName]'] = "ToSC";
    $file = "/iacrcopyright/templates/journal_copyright.html";
    break;
  case "tches":
    $substitutions['[$longName]'] = "IACR Transactions on Cryptographic Hardware and Embedded Systems";
    $substitutions['[$shortName]'] = "TCHES";
    $file = "/iacrcopyright/templates/journal_copyright.html";
    break;
  case "cic":
    $substitutions['[$longName]'] = "IACR Communications in Cryptology";
    $substitutions['[$shortName]'] = "CiC";
    $file = "/iacrcopyright/templates/cic_agreement.html";
    break;
  case "crypto": // otherwise it's a conference
  case "eurocrypt":
  case "asiacrypt":
  case "tcc":
  case "pkc":
    $file = "/iacrcopyright/templates/conference_copyright.html";
    break;
  case "rump":
  case "rwc":
    // TODO: fix this for video and slides.
    showError($Opt["iacrType"] . " doesn't use copyright form");
    $db = null;
    exit;
  default:
    showError("unknown iacrType");
    $db = null;
    exit;
}
$copyright = file_get_contents(dirname(__DIR__).$file);
foreach ($substitutions as $tag => $val) {
  $copyright = str_replace($tag, $val, $copyright);
}
// Now $copyright represents the form that is shown to the user.
// A POST is used only for submitting the copyright form, and a GET
// is used for viewing the form.
if ($Qreq->is_get()) {
  echo $copyright;
  exit;
}
/////////////////////////////////////////////////////////////////
// The rest is for a POST, which submits the copyright form.
/////////////////////////////////////////////////////////////////
// First we validate the form. We populate a few variables that could be NULL.
$country = NULL;
$agency = NULL;
$signedBy2 = NULL;
$signedBy3 = NULL;
if (empty($_POST["contact"])) {
  showError("You must fill in the name and address of the corresponding author.");
  $db = null;
  exit;
}
if (empty($_POST["signedBy1"])) {
  showError("You must sign section 1");
  $db = null;
  exit;
}
if (empty($_POST["signedBy2"]) && empty($_POST["signedBy3"])) {
  showError("You must sign section 2 or section 3");
  $db = null;
  exit;
}
if (!empty($_POST["signedBy2"]) && !empty($_POST["signedBy3"])) {
  showError("You must sign only one of section 2 or section 3 (not both)");
  $db = null;
  exit;
}
if (empty($_POST["signedBy3"])) {
  // non-government author
  $signedBy2 = $_POST["signedBy2"];
} else {
  // government author
  $signedBy3 = $_POST["signedBy3"];
  if (empty($_POST["agency"])) {
    showError("You must fill in the government agency name.");
    $db = null;
    exit;
  } else {
    $agency = $_POST["agency"];
  }       
  if (empty($_POST["country"])) {
    showError("You must fill in the country name.");
    $db = null;
    exit;
  } else {
    $country = $_POST["country"];
  }
}
// Now populate the $copyright form values to rebuild
// the form as the author saw it.
$document = new DOMDocument();
if ($document->loadHTML($copyright)) {
  $node = $document->getElementById("signedBy1");
  $node->setAttribute("value", $_POST["signedBy1"]);
  $node = $document->getElementById("contact");
  $node->nodeValue = $_POST["contact"];
  if (!empty($signedBy2)) {
    $node = $document->getElementById("signedBy2");
    $node->setAttribute("value", $signedBy2);
  }
  if (!empty($signedBy3)) {
    $node = $document->getElementById("signedBy3");
    $node->setAttribute("value", $signedBy3);
  }
  if (!empty($country)) {
    $node = $document->getelementById("country");
    $node->setAttribute("value", $country);
  }
  if (!empty($agency)) {
    $node = $document->getelementById("agency");
    $node->setAttribute("value", $agency);
  }
  $copyright = $document->saveHTML();
} else {
  showError("An error occurred in processing the form.");
  $db = null;
  exit;
}

$sql = "INSERT INTO copyright (paperId,shortName,longName,title,authorInformation,corresponding_author,form,signedBy1,signedBy2,signedBy3,agency,country) VALUES (:paperId,:shortName,:longName,:title,:authorInformation,:corresponding_author,:form,:signedBy1,:signedBy2,:signedBy3,:agency,:country)";
try {
  if (!$stmt = $db->prepare($sql)) {
    showError("unable to prepare SQL: $sql");
    $db = null;
    exit;
  }
  if (!$stmt->bindParam(":paperId", $prow->paperId, PDO::PARAM_INT) ||
      !$stmt->bindParam(":shortName", $Opt["shortName"]) ||
      !$stmt->bindParam(":longName", $Opt["longName"]) ||
      !$stmt->bindParam(":title", $prow->title) ||
      !$stmt->bindParam(":authorInformation", $prow->authorInformation) ||
      !$stmt->bindParam(":corresponding_author", $_POST["contact"]) ||
      !$stmt->bindParam(":form", $copyright) ||
      !$stmt->bindParam(":signedBy1", $_POST["signedBy1"]) ||
      !$stmt->bindParam(":signedBy2", $signedBy2) ||
      !$stmt->bindParam(":signedBy3", $signedBy3) ||
      !$stmt->bindParam(":country", $country)||
      !$stmt->bindParam(":agency", $agency)) {
    showError("unable to bind" . $db->errorInfo()[0]);
    $db = null;
    exit;
  }
  if (!$stmt->execute()) {
    showError("Unable to execute sql");
    $stmt = null;
    $db = null;
    exit;
  }

  // If this works, then set the IACR copyright option to 1 for this paper in
  // the hotcrp database.
  $optionId = getCopyrightOptionId();
  if ($optionId === NULL) die('No copyright id');
  $Conf->q("INSERT INTO PaperOption set paperId=?,optionId=?,value=?", $prow->paperId, $optionId, 1);

  echo "<html><body><h3>Your copyright form was successfully submitted</h3>";
  echo "<p><strong><a href=\"" . $Conf->hoturl("paper/" . $prow->paperId . "/edit") . "\">Return</strong></p>";
  echo "</body></html>";

  // Send an email copy to copyrightform@iacr.org
  $stmt = null;
  $db = null;
  $subject = "[" . $Opt["shortName"] . "] Copyright signed for: " . $prow->title;
  $headers  = "MIME-Version: 1.0\r\n";
  $headers .= "Content-type: text/html; charset=utf-8\r\n";
  $headers .= "From: " . $Opt["emailFrom"] . "\r\n".
              "Reply-To: " . $Opt["emailFrom"] . "\r\n" .
              "X-Mailer: PHP/" . phpversion() . "\r\n";
 
  // If this fails, then we still have a copy in the database.
  mail("copyrightform@iacr.org", $subject, $copyright, $headers);
  exit;
} catch (PDOException $e) {
  sendError("SQL error");
  $db = null;
}
?>
