<?php
require_once(dirname(__DIR__)."/conf/options.php");
require_once(dirname(__DIR__)."/src/init.php");
$qreq = null;
$conf = initialize_conf();
$nav = Navigation::get();
$qreq = initialize_request($conf, $nav);
$user = initialize_user($qreq);
if (!$user) {
  die("Unknown user");
  exit();
}
if (!$user->privChair) {
  die("Not authorized");
  exit();
}
?>