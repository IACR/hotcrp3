<?php
require "../../conf/options.php";
require "../../src/init.php";
require "/var/www/util/hotcrp/hmac.php";

function showError($msg) {
  echo json_encode(array("error" => $msg));
}

/** Set `orcid_confirmed` on the `authorlist` entries in `$entries` that have
 * an `orcid`. `$authors` are the matching Author objects. An iD is
 * confirmed iff the author's email belongs to an account whose confirmed
 * ORCID iD is the same iD; this mirrors Author::orcid_status() and
 * Contact::confirmed_orcid() (an iD stored with a trailing "?" is not
 * confirmed). IACR extension.
 * @param PDO $db
 * @param list<Author> $authors
 * @param list<array<string,mixed>> &$entries */
function setOrcidConfirmed($db, $authors, &$entries) {
  $emails = array();
  foreach ($authors as $i => $au) {
    if (isset($entries[$i]["orcid"]) && $au->email !== "") {
      $emails[] = $au->email;
    }
  }
  $confirmed = array();
  if (!empty($emails)) {
    $q = "SELECT email, orcid FROM ContactInfo WHERE orcid IS NOT NULL AND email IN ("
       . join(",", array_fill(0, count($emails), "?")) . ")";
    $stmt = $db->prepare($q);
    if ($stmt->execute($emails)) {
      while (($row = $stmt->fetch(PDO::FETCH_ASSOC))) {
        $o = $row["orcid"];
        if (strlen($o) <= 19 || $o[19] !== "?") {
          $confirmed[strtolower($row["email"])] = substr($o, 0, 19);
        }
      }
    }
  }
  foreach ($authors as $i => $au) {
    if (isset($entries[$i]["orcid"])) {
      $entries[$i]["orcid_confirmed"] =
        ($confirmed[strtolower($au->email)] ?? null) === $au->orcid;
    }
  }
}
