<?php
/* Copyright (C) 2017  Lehrstuhl für Technische Elektronik, Friedrich-Alexander-Universität Erlangen-Nürnberg */
/* https://github.com/lte-fau/MLS-Map/blob/master/LICENSE */
include "secure.php";

ignore_user_abort(true);
set_time_limit(0);

ob_start();

$pMode = $_POST["mode"];
$url = $_POST["url"];

if($pMode == "ocid")
	$mode = "ocid";
else if($pMode == "mls")
	$mode = "mls";
else
	echo "Invalid mode.";

header('Connection: close');
header("Content-Encoding: none");
header('Content-Length: '.ob_get_length());

ob_end_flush();
ob_flush();
flush();

echo "DbBuilder starting..";
exec("/usr/bin/php -q dbBuilder.php $mode $url &");
?>
