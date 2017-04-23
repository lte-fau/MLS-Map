<?php
/* Copyright (C) 2016  Lehrstuhl für Technische Elektronik, Friedrich-Alexander-Universität Erlangen-Nürnberg */
/* https://github.com/lte-fau/MLS-Map/blob/master/LICENSE */
include "local.php";
include "logHelper.php";
include "db-settings.php";

writeLog("* Starting dbBuilder for Country Outlines *");

$cMtime = microtime();
$cMtime = explode(" ",$cMtime);
$cMtime = $cMtime[1] + $cMtime[0];
$cStarttime = $cMtime;

if($argv[1] == "ocid")
{
	$cellTableName = $mlsCellTableName;
	$lacTableName = $mlsLacTableName;
	
} else if($argv[1] == "mls")
{
	$cellTableName = $mlsCellTableName;
	$lacTableName = $mlsLacTableName;
	
} else
{
	writeLog("Invalid Args.");
	exit;
}
	
$conn = pg_connect($connString . " sslmode=disable")
	or die('Could not connect: ' . pg_last_error());
		
$countryInfoParam = "MCC_AUTO_UPDATE_DATE";

writeLog("Creating table..");
pg_query($conn, "DROP TABLE IF EXISTS $countryTableName");
$sql = "CREATE TABLE $countryTableName(
			id serial PRIMARY KEY,
			mcc smallint,
			fileName text,
			outline GEOMETRY(MULTIPOLYGON, 4326),
			outlineSimp GEOMETRY(MULTIPOLYGON, 4326))";
$result = pg_query($conn, $sql);
if (!$result) {
	writeLog("An error occurred during Table creation.");
	exit;
}

writeLog("Importing files..");
$polyFiles = glob('poly/*.poly');
foreach($polyFiles as $file)
{
	$fileName = str_replace('poly/', '', $file);
	$fileContent = file($file);
	
	$polyString = "((";
	for($i = 2; $i < (count($fileContent) - 2); $i++)
	{
		if(substr($fileContent[$i], 0, 1) == ' ')
		{
			$polyString .= $fileContent[$i] . ",";
		} else
		{
			$polyString = rtrim($polyString, ',');
			$polyString .= ")),((";
			$i++;
		}
	}
	
	$polyString = rtrim($polyString, ',');
	$polyString .= "))";

	$sql = "INSERT INTO $countryTableName (fileName, outline) VALUES ('$fileName', ST_GeomFromEWKT('SRID=4326;MULTIPOLYGON($polyString)'))";
	/*
	$polyString = "";
	for($i = 2; $i < (count($fileContent) - 2); $i++)
		$polyString .= $fileContent[$i] . ",";
	
	$polyString = rtrim($polyString, ',');
	$sql = "INSERT INTO $countryTableName (fileName, outline) VALUES ('$fileName', ST_GeomFromEWKT('SRID=4326;POLYGON(($polyString))'))";
	*/
	$result = pg_query($conn, $sql);
	if (!$result) {
		writeLog("Couldn't insert country data of $fileName. Invalid Polygon?");
	}
}


$sql = "SELECT id, filename FROM $countryTableName";
$result = pg_query($conn, $sql);	
if (!$result) {
	writeLog("Couldn't read mcc data.");
	exit;
}

for ($i = 0; $i < pg_num_rows($result); $i++)
{
	$cID = pg_fetch_result($result, $i, 0);
	writeLog("Starting id $cID, Filename: " . pg_fetch_result($result, $i, 1));
	
	$res = pg_query($conn, "UPDATE $countryTableName SET outlineSimp = ST_Multi(ST_SimplifyPreserveTopology(outline, 0.3)) WHERE id = $cID");
	if (!$res) {
		writeLog("Couldn't simplify outline.");
		exit;
	}

	$sql = "SELECT mcc, COUNT(mcc) AS occ FROM $lacTableName WHERE ST_Intersects(cPos, (SELECT outlineSimp FROM $countryTableName WHERE id = $cID)) GROUP BY mcc ORDER BY occ DESC LIMIT 2";	
	$res = pg_query($conn, $sql);
	if (!$res) {
		writeLog("Couldn't read lac data.");
		exit;
	}
	
	// Fallback if LAC data inconclusive
	if((pg_num_rows($res) != 1) && (pg_fetch_result($res, 0, 1) < 3 * pg_fetch_result($res, 1, 1)))
	{
		writeLog("LAC Data insufficient. Using Cells instead..");
		$sql = "SELECT mcc, COUNT(mcc) AS occ FROM $cellTableName WHERE ST_Intersects(pos, (SELECT outlineSimp FROM $countryTableName WHERE id = $cID)) GROUP BY mcc ORDER BY occ DESC LIMIT 2";
	
		$res = pg_query($conn, $sql);
		if (!$res) {
			writeLog("Couldn't read cell data.");
			exit;
		}
	}
	
	if((pg_num_rows($res) == 1) || (pg_fetch_result($res, 0, 1) > 3 * pg_fetch_result($res, 1, 1)))
	{
		$mcc = pg_fetch_result($res, 0, 0);
		$res = pg_query($conn, "UPDATE $countryTableName SET mcc = $mcc WHERE id = $cID AND ST_AREA(outline) > 0.2");
		if (!$res) {
			writeLog("Couldn't wright mcc.");
			exit;
		}
	}
}

$sql = "UPDATE $countryTableName SET outline = ST_Multi(ST_SimplifyPreserveTopology(ST_Buffer(outline, 0.15), 0.15))";
$result = pg_query($conn, $sql);
if (!$result) {
	writeLog("Couldn't update outline.");
	exit;
}
	

writeLog("Populating info table..");
$sql = "INSERT INTO $generalInfoTableName VALUES ('$countryInfoParam', CURRENT_TIMESTAMP, null, null, null)
		ON CONFLICT (para) DO UPDATE SET time = CURRENT_TIMESTAMP, sInfo = null, iInfo = null, eInfo = null";
$result = pg_query($conn, $sql);
if (!$result) {
	writeLog("Couldn't create Builddate Entry.");
	exit;
}

$cMtime = microtime(); 
$cMtime = explode(" ",$cMtime);
$cMtime = $cMtime[1] + $cMtime[0];
$endtime = $cMtime;
$totaltime = ($endtime - $cStarttime);
writeLog("Done. Took $totaltime seconds.");
?>
