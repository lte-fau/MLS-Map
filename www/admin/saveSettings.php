<?php
/* Copyright (C) 2017  Lehrstuhl für Technische Elektronik, Friedrich-Alexander-Universität Erlangen-Nürnberg */
/* https://github.com/lte-fau/MLS-Map/blob/master/LICENSE */
include_once "secure.php";
include_once "db-settings.php";
include_once "logHelper.php";
include_once "config.php";

if(isset($_POST["ViewExtendFactor"]))
	$paraViewExtendFactor = $_POST["ViewExtendFactor"];
if(isset($_POST["MncDisableLevel"]))
	$paraMncDisableLevel = $_POST["MncDisableLevel"];
if(isset($_POST["ForceLacSortLevel"]))
	$paraForceLacSortLevel = $_POST["ForceLacSortLevel"];
if(isset($_POST["ForceClusteredLacSortLevel"]))
	$paraForceClusteredLacSortLevel = $_POST["ForceClusteredLacSortLevel"];
if(isset($_POST["ForceClusteredCellsLevel"]))
	$paraForceClusteredCellsLevel = $_POST["ForceClusteredCellsLevel"];
if(isset($_POST["CellClusterGridSize"]))
	$paraCellClusterGridSize = $_POST["CellClusterGridSize"];
if(isset($_POST["LacClusterGridSize"]))
	$paraLacClusterGridSize = $_POST["LacClusterGridSize"];
if(isset($_POST["HeatGridSize"]))
	$paraHeatGridSize = $_POST["HeatGridSize"];
if(isset($_POST["HeatMaxCellLevel"]))
	$paraHeatMaxCellLevel = $_POST["HeatMaxCellLevel"];
if(isset($_POST["HeatUseLacLevel"]))
	$paraHeatUseLacLevel = $_POST["HeatUseLacLevel"];
if(isset($_POST["MaxAvgDistanceRatio"]))
	$paraMaxAvgDistanceRatio = $_POST["MaxAvgDistanceRatio"];

$conn = pg_connect($connString)
	or die('Could not connect: ' . pg_last_error());

$sql = "TRUNCATE $settingsTableName";
$result = pg_query($conn, $sql);
if (!$result) {
	writeLog("An error occurred during truncation.");
	exit;
}

$sql = "INSERT INTO $settingsTableName(para, value) 
		VALUES ('ViewExtendFactor', $paraViewExtendFactor),
				('MncDisableLevel', $paraMncDisableLevel),
				('ForceLacSortLevel', $paraForceLacSortLevel),
				('ForceClusteredLacSortLevel', $paraForceClusteredLacSortLevel),
				('ForceClusteredCellsLevel', $paraForceClusteredCellsLevel),
				('CellClusterGridSize', $paraCellClusterGridSize),
				('LacClusterGridSize', $paraLacClusterGridSize),
				('HeatGridSize', $paraHeatGridSize),
				('HeatMaxCellLevel', $paraHeatMaxCellLevel),
				('HeatUseLacLevel', $paraHeatUseLacLevel),
				('MaxAvgDistanceRatio', $paraMaxAvgDistanceRatio)";
$result = pg_query($conn, $sql);
if (!$result) {
	writeLog("An error occurred during settings population.");
	exit;
}

echo "SAVED";
?>
