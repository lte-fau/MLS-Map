<?php
session_start();

$type = $_POST["type"];
$mcc = $_POST["mcc"];
$mnc = $_POST["mnc"];
$lac = $_POST["lac"];
$cid = $_POST["cid"];
$radio = $_POST["radio"];

if($radio != "GSM" && $radio != "UMTS" && $radio != "LTE")
	die("Invalid.");

// Create connection
include $_SERVER['DOCUMENT_ROOT'] . "/db/db-settings.php";
$conn = pg_connect($connString)
	or die('Could not connect: ' . pg_last_error());
	
if($type == 'cell')
{
	$sql = "SELECT ST_X(pos), ST_Y(pos) FROM mls WHERE mcc = $mcc AND net = $mnc AND area = $lac AND cell = $cid AND radio = $radio;";
	$result = pg_query($conn, $sql);

	if (!$result) {
	  echo "An error occurred while reading Data.";
	  exit;
	}
	
	if(pg_num_rows($result) == 1)
	{
		$lat = pg_fetch_result($result, 0, 0);
		$lon = pg_fetch_result($result, 0, 1);
		$res = "$lat|$lon";
	} else if(pg_num_rows($result) > 1)
		$res = "MULTIPLE";
	else
		$res = "NONE";
}

if($type == 'lac')
{
	$sql = "SELECT radio, cell, ST_X(pos), ST_Y(pos) FROM mls WHERE mcc = $mcc AND net = $mnc AND area = $lac AND radio = '$radio';";
	$result = pg_query($conn, $sql);

	if (!$result) {
	  echo "An error occurred while reading Data1.";
	  exit;
	}
	
	if(pg_num_rows($result) > 0)
		$res = 'LAC&&';
	else
		$res = 'ERR&&';
	
	for ($i = 0; $i < pg_num_rows($result); $i++)
		$res .= pg_fetch_result($result, $i, 0) . '|' .  pg_fetch_result($result, $i, 1) . '|' . pg_fetch_result($result, $i, 2) . '|' . pg_fetch_result($result, $i, 3) . "##";
	
	$sql = "SELECT ST_AsGeoJSON(ST_CONVEXHULL(ST_COLLECT(pos))) FROM mls WHERE mcc = $mcc AND net = $mnc AND area = $lac AND radio = '$radio' GROUP BY mcc, net, area, radio;";
	$result = pg_query($conn, $sql);
	if (!$result) {
	  echo "An error occurred while reading Data2.";
	  exit;
	}
	$res .= "&&" . pg_fetch_result($result, 0, 0);
}

pg_close($conn);
echo $res;
?>