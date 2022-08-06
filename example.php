<?php

// include("settings.php"); or define here:
$amazon_user = "amazonusername";
$amazon_pass = "amazonpassword";

require __DIR__ . '/vendor/autoload.php';
include ("amazon.class.php");

$s = new amazon($amazon_user,$amazon_pass);

$result = $s->amazon_download_files('/tmp/') ; 

var_dump($result);


