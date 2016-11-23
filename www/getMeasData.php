<?php
/* Copyright (C) 2016  Lehrstuhl für Technische Elektronik, Friedrich-Alexander-Universität Erlangen-Nürnberg */
/* https://github.com/lte-fau/MLS-Map/blob/master/LICENSE */
session_start();

$mcc = $_POST["mcc"];
$net = $_POST["net"];
$area = $_POST["area"];
$cid = $_POST["cid"];
$radio = $_POST["radio"];
/*
$mcc = 262;
$net = 1;
$area = 17586;
$cid = 2733792;
$radio = "UMTS";
	*/

if(!($radio == "GSM" || $radio == "UMTS" || $radio == "LTE"))
	die("Invalid Parameter R.");

if(!is_numeric($mcc))
	die("Invalid Parameter M.");
if(!is_numeric($net))
	die("Invalid Parameter N.");
if(!is_numeric($area))
	die("Invalid Parameter A.");
if(!is_numeric($cid))
	die("Invalid Parameter C.");


include "../db/db-settings.php";
$conn = pg_connect($connString)
	or die('Could not connect: ' . pg_last_error());
	
	
$res = "meas&&";

$sql = "SELECT ST_X(pos), ST_Y(pos) FROM ocidmeas WHERE radio = '$radio' AND mcc = $mcc AND net = $net AND area = $area AND cell = $cid";
$result = pg_query($conn, $sql);

if (!$result) {
	echo "An error occurred while reading Data.";
	exit;
}

for ($i = 0; $i < pg_num_rows($result); $i++)
{
	$res .= pg_fetch_result($result, $i, 0) . '|' . pg_fetch_result($result, $i, 1) . "##";
}

pg_close($conn);
echo $res;
?>