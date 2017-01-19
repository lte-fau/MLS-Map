<?php
/* Copyright (C) 2016  Lehrstuhl für Technische Elektronik, Friedrich-Alexander-Universität Erlangen-Nürnberg */
/* https://github.com/lte-fau/MLS-Map/blob/master/LICENSE */
include_once "local.php";

// This file does not create its own database connection since its included in dbBuilder

//__________Params___________
$mccs = array(
				array("austria", 232),
				array("germany", 262),
				array("france", 208),
				array("netherlands", 204),
				array("denmark", 238),
				array("czech-republic", 230),
				array("hungary", 216),
				array("iceland", 274),
				array("italy", 222),
				array("liechtenstein", 295),
				array("luxembourg", 270),
				array("poland", 260),
				array("romania", 226),
				array("belgium", 206),
				array("estonia", 248),
				array("faroe-islands", 288),
				array("finland", 244),
				array("latvia", 247),
				array("portugal", 268),
				//array("russia", 250), Not working, contains multiple polygons
				array("spain", 214),
				array("turkey", 286),
				array("switzerland", 228)
			);

$countryInfoParam = "MCC_UPDATE_DATE";
//___________________________

$cMtime = microtime();
$cMtime = explode(" ",$cMtime);
$cMtime = $cMtime[1] + $cMtime[0];
$cStarttime = $cMtime;

writeLog("* Starting dbBuilder for Country Outlines *");

writeLog("Creating table..");
$sql = "CREATE TABLE IF NOT EXISTS $countryTableName(
			mcc smallint,
			outline geometry(POLYGON, 4326),
			PRIMARY KEY (mcc))";
$result = pg_query($conn, $sql);
if (!$result) {
	writeLog("An error occurred during Table creation.");
	exit;
}

foreach ($mccs as $cMcc)
{
	$filename = "tmp/" . $cMcc[0] . ".poly";
    if(file_exists($filename))
	{
		writeLog("File for $cMcc[0] exists! Importing..");
		
		$fileContent = file($filename);
		
		$polyString = "";
		for($i = 2; $i < (count($fileContent) - 2); $i++)
			$polyString .= $fileContent[$i] . ",";
		
		$polyString = rtrim($polyString, ',');
		
		$sql = "INSERT INTO $countryTableName VALUES ($cMcc[1], ST_SimplifyPreserveTopology(ST_Buffer(ST_GeomFromEWKT('SRID=4326;POLYGON(($polyString))'), 0.15), 0.1))
				ON CONFLICT (mcc) DO UPDATE SET outline = ST_SimplifyPreserveTopology(ST_Buffer(ST_GeomFromEWKT('SRID=4326;POLYGON(($polyString))'), 0.15), 0.1)";
		$result = pg_query($conn, $sql);	
		if (!$result) {
			writeLog("Couldn't insert mcc data.");
			exit;
		}
	}
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
