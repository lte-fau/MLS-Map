<?php
/* Copyright (C) 2017  Lehrstuhl für Technische Elektronik, Friedrich-Alexander-Universität Erlangen-Nürnberg */
/* https://github.com/lte-fau/MLS-Map/blob/master/LICENSE */

// Create connection
include_once "admin/db-settings.php";
$conn = pg_connect($connString)
	or die('Could not connect: ' . pg_last_error());


$sql = "SELECT * FROM $settingsTableName";
$result = pg_query($conn, $sql);
if (!$result) {
	writeLog("An error occurred during settings population.");
	exit;
}

for ($i = 0; $i < pg_num_rows($result); $i++)
{
	switch(pg_fetch_result($result, $i, 0))
	{
		case 'ViewExtendFactor':
			$paraViewExtendFactor = pg_fetch_result($result, $i, 1);
			break;
		case 'MncDisableLevel':
			$paraMncDisableLevel = pg_fetch_result($result, $i, 1);
			break;
		case 'ForceLacSortLevel':
			$paraForceLacSortLevel = pg_fetch_result($result, $i, 1);
			break;
		case 'ForceClusteredLacSortLevel':
			$paraForceClusteredLacSortLevel = pg_fetch_result($result, $i, 1);
			break;
		case 'ForceClusteredCellsLevel':
			$paraForceClusteredCellsLevel = pg_fetch_result($result, $i, 1);
			break;
		case 'CellClusterGridSize':
			$paraCellClusterGridSize = pg_fetch_result($result, $i, 1);
			break;
		case 'LacClusterGridSize':
			$paraLacClusterGridSize = pg_fetch_result($result, $i, 1);
			break;
		case 'HeatGridSize':
			$paraHeatGridSize = pg_fetch_result($result, $i, 1);
			break;
		case 'HeatMaxCellLevel':
			$paraHeatMaxCellLevel = pg_fetch_result($result, $i, 1);
			break;
		case 'HeatUseLacLevel':
			$paraHeatUseLacLevel = pg_fetch_result($result, $i, 1);
			break;
		case 'MaxAvgDistanceRatio':
			$paraMaxAvgDistanceRatio = pg_fetch_result($result, $i, 1);
			break;
		default:
			writeLog("Invalid Parameter in Settingstable.");
	}
}

pg_close($conn);
?>
