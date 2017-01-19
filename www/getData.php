<?php
/* Copyright (C) 2017  Lehrstuhl für Technische Elektronik, Friedrich-Alexander-Universität Erlangen-Nürnberg */
/* https://github.com/lte-fau/MLS-Map/blob/master/LICENSE */
session_start();

function lon2x($lon) { return deg2rad($lon) * 6378137.0; }
function lat2y($lat) { return log(tan(M_PI_4 + deg2rad($lat) / 2.0)) * 6378137.0; }
function x2lon($x) { return rad2deg($x / 6378137.0); }
function y2lat($y) { return rad2deg(2.0 * atan(exp($y / 6378137.0)) - M_PI_2); }

$hash = $_POST["hash"];
$latUL = $_POST["latUL"];
$lonUL = $_POST["lonUL"];
$latOR = $_POST["latOR"];
$lonOR = $_POST["lonOR"];
$exMode = $_POST["mode"];
$zoom = $_POST["zoom"];
$radios = $_POST["radios"];
$nets = $_POST["nets"];
$ageStamp = $_POST["ageStamp"];
$dataSource = $_POST["dataSource"];

include "admin/db-settings.php";
include "getSettings.php";


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


// Mode Select
if($exMode == "mnc")
	$mode = "mnc";
else if($exMode == "heat")
	$mode = "heat";
else if($exMode == "lacSort" || $zoom <= $paraForceLacSortLevel)
{
	$mode = "lacSort";
	if($zoom <= $paraForceClusteredLacSortLevel) $mode .= "Clustered";
}else if($zoom > $paraForceClusteredCellsLevel)
	$mode = "cell"; 
else
	$mode = "cluster";

// Change Bounds to get a little bit more than is visible
$latdif = $latOR - $latUL;
$londif = $lonOR - $lonUL;

$latUL -= $latdif * $paraViewExtendFactor;
$lonUL -= $londif * $paraViewExtendFactor;
$latOR += $latdif * $paraViewExtendFactor;
$lonOR += $londif * $paraViewExtendFactor;

if($latUL <= -90)
	$latUL = -89.99;
if($latUL >= 90)
	$latUL = 89.99;
if($latOR <= -90)
	$latOR = -89.99;
if($latOR >= 90)
	$latOR = 89.99;

if($lonUL < -180)
	$lonUL = -180;
if($lonUL > 180)
	$lonUL = 180;
if($lonOR < -180)
	$lonOR = -180;
if($lonOR > 180)
	$lonOR = 180;


$inStringRadio = "";
$inStringNet = " AND net IN (";
$inStringTime = " AND updated > ";


$radioArray = explode('|', $radios, -1);
for($i = 0; $i < count($radioArray); $i++)
{
	if(!($radioArray[$i] == "GSM" || $radioArray[$i] == "UMTS" || $radioArray[$i] == "LTE"))
		die("Invalid Parameters.");
	
	if($i == (count($radioArray) - 1))
		$inStringRadio .= "'" . $radioArray[$i] . "'";
	else
		$inStringRadio .= "'" . $radioArray[$i] . "', ";
}

if($mode != "mnc")
{
	// Build MNC Select String:
	if($nets == "ALL")
		$inStringNet = "";
	else
	{
		$netArray = explode('|', $nets, -1);
		for($i = 0; $i < count($netArray); $i++)
		{
			if(!is_numeric($netArray[$i]))
				die("Invalid Parameters.");
			
			if($i == (count($netArray) - 1))
				$inStringNet .= "'" . $netArray[$i] . "')";
			else
				$inStringNet .= "'" . $netArray[$i] . "', ";
		}
	}
}

if(!is_numeric($ageStamp))
	die("Invalid Parameters.");

if($ageStamp != 0)
	$inStringTime .= $ageStamp;
else
	$inStringTime = "";

$conn = pg_connect($connString)
	or die('Could not connect: ' . pg_last_error());
	
// Do different querys depending on inputs Strings
if($mode == "cell")
{
	$res = $hash . "&&cell&&";
	
	$sql = "SELECT radio, mcc, net, area, cell, ST_X(pos), ST_Y(pos), updated, problem FROM $mainTableName WHERE pos && ST_MakeEnvelope (
					$lonUL, $latUL, $lonOR, $latOR, 4326) AND radio IN ($inStringRadio) $inStringNet $inStringTime";
	$result = pg_query($conn, $sql);
	
	if (!$result) {
		echo "An error occurred while reading Data.";
		exit;
	}
	
	for ($i = 0; $i < pg_num_rows($result); $i++)
	{
		$res .= pg_fetch_result($result, $i, 0) . '|' . pg_fetch_result($result, $i, 1) . '|' . pg_fetch_result($result, $i, 2) . '|' .  pg_fetch_result($result, $i, 3) . '|' .  
				pg_fetch_result($result, $i, 4) . '|' . pg_fetch_result($result, $i, 5) . '|' . pg_fetch_result($result, $i, 6) . '|' . pg_fetch_result($result, $i, 7) . '|' . pg_fetch_result($result, $i, 8) . "##";
	}
	
}else if($mode == "cluster")
{
	$res = $hash . "&&cluster&&";
	
	$xySplit = $paraCellClusterGridSize;
	
	$xUL = lon2x($lonUL);
	$xOR = lon2x($lonOR);
	$yUL = lat2y($latUL);
	$yOR = lat2y($latOR);
	
	$yModifier = abs($yOR - $yUL) / $xySplit;
	$xModifier = abs($xOR - $xUL) / $xySplit;
	
	$baseY = $yModifier * round($yUL / $yModifier);
	$baseX = $xModifier * round($xUL / $xModifier);
	
	$latModifier = abs($latOR - $latUL) / $xySplit;
	$lonModifier = abs($lonOR - $lonUL) / $xySplit;

	$baseLat = y2lat($baseY);
	$baseLon = x2lon($baseX);
	
	for($i = 0; $i < $xySplit; $i++)
	{
		for($j = 0; $j < $xySplit; $j++)
		{
			$latULBound = $baseLat + $latModifier*$j;
			$lonULBound = $baseLon + $lonModifier*$i;
			$latORBound = $baseLat + $latModifier*($j+1);
			$lonORBound = $baseLon + $lonModifier*($i+1);
			
			$sql = "SELECT COUNT(*) FROM $mainTableName WHERE pos && ST_MakeEnvelope (
					$lonULBound, $latULBound, $lonORBound, $latORBound, 4326) AND radio IN ($inStringRadio) $inStringNet $inStringTime";
		
			$result = pg_query($conn, $sql);
			
			if (!$result) {
				echo "An error occurred while reading Data.";
				exit;
			}
			
			$centerLat = ($latULBound + $latORBound)/2;
			$centerLon = ($lonULBound + $lonORBound)/2;
			
			$sValue = pg_fetch_result($result, 0, 0);
			if($sValue > 0)
				$res .= $centerLat . "|" . $centerLon . "|" . $sValue . "##";	
		}
	}
}else if($mode == "lacSort" && $exMode == "lacSort")
{
	$res = $hash . "&&lacSort&&";
	
	$sql = "SELECT area, radio, net, mcc, size, ST_X(cPos), ST_Y(cPos), ST_AsGeoJSON(outline) 
			FROM $lacTableName 
			WHERE cPos && ST_MakeEnvelope ($lonUL, $latUL, $lonOR, $latOR, 4326) AND radio IN ($inStringRadio) $inStringNet";
	
	$result = pg_query($conn, $sql);
	
	if (!$result) {
		echo "An error occurred while reading Data.";
		exit;
	}
	
	for ($i = 0; $i < pg_num_rows($result); $i++)
	{
		$res .= pg_fetch_result($result, $i, 0) . '|' .  pg_fetch_result($result, $i, 1) . '|' . pg_fetch_result($result, $i, 2) . '|' . 
				pg_fetch_result($result, $i, 3) . '|' . pg_fetch_result($result, $i, 4) . '|' . pg_fetch_result($result, $i, 5) . '|' . 
				pg_fetch_result($result, $i, 6) . '|' . pg_fetch_result($result, $i, 7) . "##";
	}
}else if($mode == "lacSort" && $exMode == "norm")
{
	$res = $hash . "&&cluster&&";
	
	$sql = "SELECT size, ST_X(cPos), ST_Y(cPos)
			FROM $lacTableName 
			WHERE cPos && ST_MakeEnvelope ($lonUL, $latUL, $lonOR, $latOR, 4326) AND radio IN ($inStringRadio) $inStringNet";
	
	$result = pg_query($conn, $sql);
	
	if (!$result) {
		echo "An error occurred while reading Data.";
		exit;
	}
	
	for ($i = 0; $i < pg_num_rows($result); $i++)
		$res .= pg_fetch_result($result, $i, 1) . '|' .  pg_fetch_result($result, $i, 0) . '|' . pg_fetch_result($result, $i, 2) . "##";
}else if($mode == "lacSortClustered")
{
	if($exMode == "norm")
		$res = $hash . "&&cluster&&";
	else
		$res = $hash . "&&lacSortClustered&&";
	
	$xySplit = $paraLacClusterGridSize;
	
	$xUL = lon2x($lonUL);
	$xOR = lon2x($lonOR);
	$yUL = lat2y($latUL);
	$yOR = lat2y($latOR);
	
	$yModifier = abs($yOR - $yUL) / $xySplit;
	$xModifier = abs($xOR - $xUL) / $xySplit;
	
	$baseY = $yModifier * round($yUL / $yModifier);
	$baseX = $xModifier * round($xUL / $xModifier);
	
	$latModifier = abs($latOR - $latUL) / $xySplit;
	$lonModifier = abs($lonOR - $lonUL) / $xySplit;

	$baseLat = y2lat($baseY);
	$baseLon = x2lon($baseX);
	
	for($i = 0; $i < $xySplit; $i++)
	{
		for($j = 0; $j < $xySplit; $j++)
		{
			$latULBound = $baseLat + $latModifier*$j;
			$lonULBound = $baseLon + $lonModifier*$i;
			$latORBound = $baseLat + $latModifier*($j+1);
			$lonORBound = $baseLon + $lonModifier*($i+1);
			
			if($exMode == "norm")
				$sql = "SELECT SUM(size) FROM $lacTableName WHERE cPos && ST_MakeEnvelope (
					$lonULBound, $latULBound, $lonORBound, $latORBound, 4326) AND radio IN ($inStringRadio) $inStringNet";
			else
				$sql = "SELECT count(*) FROM $lacTableName WHERE cPos && ST_MakeEnvelope (
					$lonULBound, $latULBound, $lonORBound, $latORBound, 4326) AND radio IN ($inStringRadio) $inStringNet";
			
		
			$result = pg_query($conn, $sql);
			
			if (!$result) {
				echo "An error occurred while reading Data.";
				exit;
			}
			
			$centerLat = ($latULBound + $latORBound)/2;
			$centerLon = ($lonULBound + $lonORBound)/2;
			
			$sValue = pg_fetch_result($result, 0, 0);
			if($sValue > 0)
				$res .= $centerLat . "|" . $centerLon . "|" . $sValue . "##";	
		}
	}
}else if($mode == "heat")
{
	$res = $hash . "&&heat&&";
	
	if($zoom >= $paraHeatMaxCellLevel)
	{
		$sql = "SELECT ST_X(pos), ST_Y(pos) FROM $mainTableName WHERE pos && ST_MakeEnvelope (
			$lonUL, $latUL, $lonOR, $latOR, 4326) AND radio IN ($inStringRadio) $inStringNet $inStringTime";
		
		$result = pg_query($conn, $sql);
		if (!$result) {
			echo "An error occurred while reading Data.";
			exit;
		}
		
		for ($i = 0; $i < pg_num_rows($result); $i++)
			$res .= pg_fetch_result($result, $i, 0) . '|' .  pg_fetch_result($result, $i, 1) . '|1##';
		
	}else
	{
		$xySplit = $paraHeatGridSize;
		
		$xUL = lon2x($lonUL);
		$xOR = lon2x($lonOR);
		$yUL = lat2y($latUL);
		$yOR = lat2y($latOR);
		
		$yModifier = abs($yOR - $yUL) / $xySplit;
		$xModifier = abs($xOR - $xUL) / $xySplit;
		
		$baseY = $yModifier * round($yUL / $yModifier);
		$baseX = $xModifier * round($xUL / $xModifier);
		
		$latModifier = abs($latOR - $latUL) / $xySplit;
		$lonModifier = abs($lonOR - $lonUL) / $xySplit;

		$baseLat = y2lat($baseY);
		$baseLon = x2lon($baseX);
		
		for($i = 0; $i < $xySplit; $i++)
		{
			for($j = 0; $j < $xySplit; $j++)
			{
				$latULBound = $baseLat + $latModifier*$j;
				$lonULBound = $baseLon + $lonModifier*$i;
				$latORBound = $baseLat + $latModifier*($j+1);
				$lonORBound = $baseLon + $lonModifier*($i+1);
				
				if($zoom <= $paraHeatUseLacLevel)
					$sql = "SELECT SUM(size) FROM $lacTableName WHERE cPos && ST_MakeEnvelope (
						$lonULBound, $latULBound, $lonORBound, $latORBound, 4326) AND radio IN ($inStringRadio) $inStringNet";
				else
					$sql = "SELECT COUNT(*) FROM $mainTableName WHERE pos && ST_MakeEnvelope (
							$lonULBound, $latULBound, $lonORBound, $latORBound, 4326) AND radio IN ($inStringRadio) $inStringNet $inStringTime";
			
				$result = pg_query($conn, $sql);
				
				if (!$result) {
					echo "An error occurred while reading Data." . pg_last_error($conn);
					exit;
				}
				$centerLat = ($latULBound + $latORBound)/2;
				$centerLon = ($lonULBound + $lonORBound)/2;
				
				$sValue = pg_fetch_result($result, 0, 0);
				if($sValue > 0)
					$res .= $centerLon . "|" . $centerLat . "|" . $sValue . "##";	
			}
		}
	}		
}else if($mode == "mnc")
{
	$res = $hash . "&&mnc&&";
			
	if($zoom <= $paraMncDisableLevel)
		$res .= "DISABLED";
	else
	{	
		$sql = "SELECT DISTINCT net FROM $mainTableName WHERE pos && ST_MakeEnvelope ($lonUL, $latUL, $lonOR, $latOR, 4326) AND radio IN ($inStringRadio) $inStringTime AND problem = 0 ORDER BY net";
		$result = pg_query($conn, $sql);

		if (!$result) {
			echo "No such cells Found.";
			exit;
		}
		
		for ($i = 0; $i < pg_num_rows($result); $i++)
			$res .= pg_fetch_result($result, $i, 0) . '|';
	}
}

pg_close($conn);
echo $res;
?>