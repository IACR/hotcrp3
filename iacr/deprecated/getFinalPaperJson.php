<?php
require "finalLib.php";
$paperdata = getFinalPaperData();
header("Content-Type: application/json");
echo json_encode($paperdata);

?>
