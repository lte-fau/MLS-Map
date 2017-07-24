<?php
/* Copyright (C) 2017  Lehrstuhl für Technische Elektronik, Friedrich-Alexander-Universität Erlangen-Nürnberg */
/* https://github.com/lte-fau/MLS-Map/blob/master/LICENSE */

$key = $_GET["key"];
$dataSource = $_GET["src"];
$mcc = $_GET["mcc"];
$net = $_GET["mnc"];
$area = $_GET["lac"];
$cid = $_GET["cid"];
$radio = $_GET["radio"];

include "../admin/db-settings.php";

if($key != $apiKey)
{
	echo "Wrong Key.";
	exit;
}

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

if($dataSource == "ocid")
{
	$mainTableName = $ocidCellTableName;
	$lacTableName = $ocidLacTableName;
} else if($dataSource == "mls")
{
	$mainTableName = $mlsCellTableName;
	$lacTableName = $mlsLacTableName;
} else // Default
{
	$mainTableName = $mlsCellTableName;
	$lacTableName = $mlsLacTableName;
}

$conn = pg_connect($connString)
	or die('Could not connect: ' . pg_last_error());


$sql = "SELECT ST_X(pos), ST_Y(pos), updated, problem FROM $mainTableName WHERE radio = '$radio' AND mcc = $mcc AND net = $net AND area = $area AND cell = $cid";
$result = pg_query($conn, $sql);
if (!$result) {
	echo "An error occurred while reading Data.";
	exit;
}

$arr = array('radio' => $radio, 'mcc' => (int)$mcc, 'mnc' => (int)$net, 'lac' => (int)$area, 'cid' => (int)$cid, 'lon' => (float)pg_fetch_result($result, 0, 0),
			'lat' => (float)pg_fetch_result($result, 0, 1), 'updated' => (int)pg_fetch_result($result, 0, 2), 'status' => (int)pg_fetch_result($result, 0, 3));


pg_close($conn);
echo json_encode($arr);
?>
