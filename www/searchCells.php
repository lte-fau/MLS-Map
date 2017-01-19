<?php
/* Copyright (C) 2016  Lehrstuhl für Technische Elektronik, Friedrich-Alexander-Universität Erlangen-Nürnberg */
/* https://github.com/lte-fau/MLS-Map/blob/master/LICENSE */
session_start();

$type = $_POST["type"];
$mcc = $_POST["mcc"];
$mnc = $_POST["mnc"];
$lac = $_POST["lac"];

if(isset($_POST["cid"]))
	$cid = $_POST["cid"];

$radio = $_POST["radio"];
$dataSource = $_POST["dataSource"];
$ageStamp = $_POST["ageStamp"];

include "admin/db-settings.php";

if($dataSource == "ocid")
{
	$mainTableName = $ocidCellTableName;
	$lacTableName = $ocidLacTableName;
} else if($dataSource == "mls")
{
	$mainTableName = $mlsCellTableName;
	$lacTableName = $mlsLacTableName;
} else
	die("Invalid.");

if($radio != "GSM" && $radio != "UMTS" && $radio != "LTE")
	die("Invalid.");

if(!is_numeric($mcc))
	die("Invalid Parameter M.");
if(!is_numeric($mnc))
	die("Invalid Parameter N.");
if(!is_numeric($lac))
	die("Invalid Parameter A.");
if(isset($cid) && !is_numeric($cid))
	die("Invalid Parameter C.");
if(!is_numeric($ageStamp))
	die("Invalid Parameter S.");


// Create connection
$conn = pg_connect($connString)
	or die('Could not connect: ' . pg_last_error());
	
if($type == 'cell')
{
	$sql = "SELECT ST_X(pos), ST_Y(pos), updated, problem FROM $mainTableName WHERE mcc = $mcc AND net = $mnc AND area = $lac AND cell = $cid AND radio = '$radio'";
	$result = pg_query($conn, $sql);

	if (!$result) {
	  echo "An error occurred while reading Data.";
	  exit;
	}
	
	if(pg_num_rows($result) == 1)
		$res = pg_fetch_result($result, 0, 0) . "|" . pg_fetch_result($result, 0, 1) . "|" . pg_fetch_result($result, 0, 2) . "|" . pg_fetch_result($result, 0, 3);
	else if(pg_num_rows($result) > 1)
		$res = "MULTIPLE";
	else
		$res = "NONE";
} else if($type == 'lac')
{
	$inStringTime = " AND updated > ";

	if($ageStamp != 0)
		$inStringTime .= $ageStamp;
	else
		$inStringTime = "";
	
	$sql = "SELECT cell, ST_X(pos), ST_Y(pos), updated, problem FROM $mainTableName WHERE mcc = $mcc AND net = $mnc AND area = $lac AND radio = '$radio' $inStringTime";
	$result = pg_query($conn, $sql);

	if (!$result) {
		echo "An error occurred while reading Data1.";
		exit;
	}
	
	if(pg_num_rows($result) > 0)
	{
		$res = 'LAC&&';
		
		for ($i = 0; $i < pg_num_rows($result); $i++)
			$res .= pg_fetch_result($result, $i, 0) . '|' .  pg_fetch_result($result, $i, 1) . '|' . pg_fetch_result($result, $i, 2) . '|' . pg_fetch_result($result, $i, 3) . '|' . pg_fetch_result($result, $i, 4) . "##";
		
		$sql = "SELECT ST_AsGeoJSON(ST_CONVEXHULL(ST_COLLECT(pos))) FROM $mainTableName WHERE mcc = $mcc AND net = $mnc AND area = $lac AND radio = '$radio' AND problem = 0 GROUP BY mcc, net, area, radio";
		$result = pg_query($conn, $sql);
		if (!$result) {
			echo "An error occurred while reading Data2.";
			exit;
		}
		
		$res .= "&&";
		if(pg_num_rows($result) > 0)
			$res .= pg_fetch_result($result, 0, 0);

		$sql = "SELECT ST_AsGeoJSON(outline), size, invalidCells FROM $lacTableName WHERE mcc = $mcc AND net = $mnc AND area = $lac AND radio = '$radio'";
		$result = pg_query($conn, $sql);
		if (!$result) {
			echo "An error occurred while reading Data3.";
			exit;
		}
		$res .= "&&" . pg_fetch_result($result, 0, 0) . '|' . pg_fetch_result($result, 0, 1) . '|' . pg_fetch_result($result, 0, 2);
	}
	else
		$res = 'ERR&&';
} else
	die("Invalid Parameters");

pg_close($conn);
echo $res;
?>
