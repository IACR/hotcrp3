<?php
require_once(dirname(__DIR__)."/conf/options.php");
require_once(dirname(__DIR__)."/src/init.php");
initialize_conf();
initialize_request();
if (!Contact::$main_user->privChair) {
  die("Not authorized");
  exit();
}
?>