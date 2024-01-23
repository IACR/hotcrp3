<?php

require __DIR__ ."/../../conf/options.php";

// The iacr_paperid should be globally unique across all hotcrp instances. It should be short,
// because it is hashed to create the DOI for the paper. It should not be parsed to extract
// other elements like the volume number.

function iacr_paperid($paperId) {
  global $Opt;
  $suffix = strval($Opt['volume']) . '-' . strval($Opt['issue']) . '-' . strval($paperId);
  switch ($Opt['iacrType']) {
    case 'cic':
      return 'cc' . $suffix;
    case 'tches':
      return 'tc' . $suffix;
    case 'tosc':
      return 'to' . $suffix;
    case 'crypto':
      return 'cr' . $suffix;
    case 'eurocrypt':
      return 'eu' . $suffix;
    case 'asiacrypt':
      return 'as' . $suffix;
    case 'tcc':
      return 'tc' . $suffix;
    case 'pkc':
      return 'pk' . $suffix;
    default:
      return $Opt['iacrType'] . $suffix;
  }
}
?>
