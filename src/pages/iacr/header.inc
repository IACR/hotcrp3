<?php
// src/pages/iacr/header.inc -- page head for IACR chair pages.
// Part of the IACR extensions; not part of upstream HotCRP.
// Included by IACR_Page::print_header(), which defines $title, $siteurl,
// $home_url, $iacr_url, and $short_name (all HTML-safe), and has $qreq.
// Page styles, including dark mode, are in stylesheets/iacr-pages.css,
// which must load after Bootstrap.
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title><?= $title ?></title>
    <?php IACR_Page::print_theme_style($qreq); ?>
    <link href="<?= $siteurl ?>stylesheets/style.css" rel="stylesheet">
    <link href="https://iacr.org/libs/css/bootstrap/bootstrap-5.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= $siteurl ?>stylesheets/iacr.css" rel="stylesheet">
    <link href="<?= $siteurl ?>stylesheets/iacr-pages.css" rel="stylesheet">
    <link rel="shortcut icon" href="https://iacr.org/favicon.ico">
    <script src="https://iacr.org/libs/js/jquery/3.3.1/jquery.min.js"></script>
    <script src="https://iacr.org/libs/css/bootstrap/bootstrap-5.3/js/bootstrap.bundle.min.js"></script>
  </head>
  <body>
    <div id="top">
      <div id="h-site">
        <a class="q" href="<?= $home_url ?>"><span class="header-site-name"><?= $short_name ?> Home</span></a>
      </div>
    <h2 class="float-end mt-4 me-4"><a href="<?= $iacr_url ?>">IACR HotCRP integration</a></h2>
    </div>
    
