<?php
require "../../conf/options.php";
require "../../src/init.php";
require "/var/www/util/hotcrp/hmac.php";

function showError($msg) {
  echo json_encode(array("error" => $msg));
}

