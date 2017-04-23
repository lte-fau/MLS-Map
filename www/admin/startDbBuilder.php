<?php
/* Copyright (C) 2017  Lehrstuhl für Technische Elektronik, Friedrich-Alexander-Universität Erlangen-Nürnberg */
/* https://github.com/lte-fau/MLS-Map/blob/master/LICENSE */
include "secure.php";
include "logHelper.php";

ignore_user_abort(true);
set_time_limit(0);

ob_start();

$pType = $_POST["type"];
$pMode = $_POST["mode"];

if($pType == "cells")
	$type = "cells";
else if($pType == "country")
	$type = "country";
else
	die("Invalid type.");

if($pMode == "ocid")
	$mode = "ocid";
else if($pMode == "mls")
	$mode = "mls";
else
	die("Invalid mode.");

echo "Starting..";

header('Connection: close');
header('Content-Encoding: none');
header('Content-Length: '.ob_get_length());

ob_end_flush();
ob_flush();
flush();

if($type == "cells")
{
	writeLog("Executing DbBuilder..");
	exec("/usr/bin/php -q dbBuilder.php $mode &");
} else if($type == "country")
{
	writeLog("Executing CountryDbBuilder..");
	exec("/usr/bin/php -q countryDbBuilderAuto.php $mode &");
}
?>
