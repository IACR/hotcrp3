<?php
// This is in init.php now.
// global $Me;
// if (!$Me->is_signed_in() || !$Me->privChair) {
//   header("Location: ..");
// }
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>IACR functionality of HotCRP</title>
    <link href="../stylesheets/style.css" rel="stylesheet">
    <link href="https://iacr.org/libs/css/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../stylesheets/iacr.css" rel="stylesheet">
    <link rel="shortcut icon" href="https://iacr.org/favicon.ico">
    <style>
     .leftnav {
       background-color:  #ecf2f4;
     }
     .leftnav > div {
       margin-top: 10px;
       margin-bottom: 10px;
     }
     div.navheader {
       margin-top: 10px;
     }
     .leftnav a.navitem {
       border-radius: 5px;
       margin-left: 5px;
       color:black;
       text-decoration: none;
       padding: 5px 5px 5px 10px;
       border: 1px solid transparent;
     }
     .leftnav a.active {
       border: 1px solid red;
       color: red;
       background-color: white;
     }
     .leftnav a:hover {
       border: 1px solid green;
       color: green;
       background-color: white;
     }
     /* hotcrp overrides btn and btn-primary, so we use .button
        and .button-primary and copy from bootstrap 4.3. */
     .button {
        background-image: none;
        display: inline-block;
        font-weight: 400;
        color: #212529;
        text-align: center;
        vertical-align: middle;
        -webkit-user-select: none;
        -moz-user-select: none;
       -ms-user-select: none;
       user-select: none;
       background-color: transparent;
       border: 1px solid transparent;
       padding: 0.375rem 0.75rem;
       font-size: 1rem;
       line-height: 1.5;
       border-radius: 0.25rem;
       transition: color 0.15s ease-in-out, background-color 0.15s ease-in-out, border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
     }
     @media (prefers-reduced-motion: reduce) {
       .button {
         transition: none;
       }
     }
     .button:hover {
        background: none;
        background-image: none;
        color: #212529;
        text-decoration: none;
     }
     .button-primary {
        color: #fff;
        background-color: #007bff;
        border-color: #007bff;
     }
     .button-primary:hover {
        color: #fff;
        background-color: #0069d9;
        border-color: #0062cc;
     }
     .button-primary:focus, .button-primary.focus {
        box-shadow: 0 0 0 0.2rem rgba(38, 143, 255, 0.5);
     }
     .header-site-name {
       font-size: 24px;
     }
     #header-site {
       min-height: 120px;
     }
    </style>
    <script src="https://iacr.org/libs/js/jquery/3.3.1/jquery.min.js"></script>
    <script src="https://iacr.org/libs/css/bootstrap/dist/js/bootstrap.min.js"></script>
  </head>
  <body>
    <div id="top">
      <div id="h-site">
        <a class="q" href="/<?php echo $Opt['dbName'];?>/"><span class="header-site-name"><?php echo $Opt['shortName'];?> Home</span></a>
      </div>
    <h2 class="float-right mt-4 mr-4"><a href="/<?php echo $Opt['dbName'] . '/iacr/';?>">IACR HotCRP integration</a></h2>
    </div>
    
