<?php
/* Copyright (C) 2017  Lehrstuhl für Technische Elektronik, Friedrich-Alexander-Universität Erlangen-Nürnberg */
/* https://github.com/lte-fau/MLS-Map/blob/master/LICENSE */
include "local.php";
include "db-settings.php";
include "../getSettings.php";
include "config.php";
include "logHelper.php";

$mtime = microtime();
$mtime = explode(" ",$mtime);
$mtime = $mtime[1] + $mtime[0];
$starttime = $mtime;

writeLog("");
writeLog("*******************************************");
writeLog("******* Starting dbBuilder for $argv[1] *******");
writeLog("*******************************************");
writeLog("");


	//__________Params___________
if($argv[1] == "ocid")
{

	$fileName = "tmp/OCIDTowers.csv.gz";

	$finalTableName = $ocidCellTableName;
	$finalLacTableName = $ocidLacTableName;

	$finalGixName = "ocid_gix";
	$finalLacGixName = "ocid_lac_gix";

	$infoParam = "OCID_BUILD_DATE";
	
	$dataURL = "http://opencellid.org/downloads/?apiKey=" . $ocidAPIKey . "&filename=cell_towers.csv.gz";
} else if($argv[1] == "mls")
{
	$fileName = "tmp/MLSTowers.csv.gz";

	$finalTableName = $mlsCellTableName;
	$finalLacTableName = $mlsLacTableName;
	$finalGixName = "mls_gix";
	$finalLacGixName = "mls_lac_gix";

	$infoParam = "MLS_BUILD_DATE";
	
	$dataURL = $argv[2];

} else
{
	writeLog("Invalid Args.");
	exit;
}
	
$tempImportName = "bulkcells";
$tempTableName = "tempCells";
$tempLacTableName = "tempLACs";

$tempGixName = "temp_gix";
$tempLacGixName = "temp_lac_gix";
	//___________________________

	
if($dataURL != '')
{
	if(substr($dataURL, 0, 49) !== "https://d17pt8qph6ncyq.cloudfront.net/export/MLS-" && $argv[1] != "ocid")
	{
		writeLog("URL invalid.");
		exit;
	}
	writeLog("Downloading datafile..");
	$res = file_put_contents($fileName, fopen("$dataURL", 'r'));
	if($res === false)
	{
		writeLog("Download failed.");
		exit;
	}
	else
		writeLog(($res / 1000) . " KB loaded.");
} else
	writeLog("No URL given. Using local file instead.");

writeLog("Extracting file..");
$buffer_size = 1048576; // 1MiB
$outputFileName = str_replace('.gz', '', $fileName); 
$srcFileName = $outputFileName;

$file = gzopen($fileName, 'rb');
$outputFile = fopen($outputFileName, 'wb'); 

if($file == false || $outputFile == false)
{
	writeLog("Failed to open file.");
	exit;
}

while (!gzeof($file)) {
    fwrite($outputFile, gzread($file, $buffer_size));
}

fclose($outputFile);
gzclose($file);

if($argv[1] == "mls")
{
	writeLog("Removing carriage return characters..");
	
	$file_read = fopen($outputFileName, "r");
	$outputFileName = str_replace('tmp/', '', $outputFileName); 
	$outputFileName = "Mod" . $outputFileName;
	$file_write = fopen('tmp/' . $outputFileName, "w+");
	
	if($file_read == false || $file_write == false)
	{
		writeLog("Failed to open file.");
		exit;
	}
	
	while(!feof($file_read))
	{
		$file_line = fgets($file_read);
		$file_trim = str_replace("\r", '', $file_line); 
		fwrite($file_write, $file_trim);
	}
	
	fclose($file_read);
	fclose($file_write);
	
	unlink($srcFileName);
}

$srcFileName = str_replace('tmp/', '', $outputFileName); 

// Create connection
$conn = pg_connect($connString . " sslmode=disable")
	or die('Could not connect: ' . pg_last_error());

writeLog("Connected to database successfully.");

writeLog("Creating bulk-import table..");
pg_query($conn, "DROP TABLE IF EXISTS $tempTableName");
pg_query($conn, "DROP TABLE IF EXISTS $tempLacTableName");

pg_query($conn, "DROP TABLE IF EXISTS $tempImportName");

// COPY is fastest wenn done in the same transaction as CREATE TABLE
pg_query($conn, "BEGIN");

$result = pg_query($conn, "CREATE UNLOGGED TABLE $tempImportName(
	radio text,
	mcc smallint, 
	net smallint,
	area integer,
	cell integer,
	unit integer,
	lon double precision,
	lat double precision,
	range integer,
	samples integer,
	changeable smallint,
	created bigint,
	updated bigint,
	averagesignal smallint)");
if (!$result) {
	writeLog("An error occurred during Table creation.");
	exit;
}

writeLog("Importing Data..");
$result = pg_query($conn, "SELECT import_csv_file_to_table('$tempImportName', '$srcFileName')");
if (!$result) {
	writeLog("An error occurred during Bulk import.");
	exit;
}
pg_query($conn, "COMMIT");

writeLog("Deleting extracted file..");
unlink("tmp/" . $srcFileName);

writeLog("Creating main table..");
$result = pg_query($conn, "CREATE UNLOGGED TABLE $tempTableName(
	radio text,
	mcc smallint, 
	net smallint,
	area integer,
	cell integer,
	unit integer,
	pos geometry(POINT, 4326),
	range integer,
	samples integer,
	changeable smallint,
	created bigint,
	updated bigint,
	averagesignal smallint,
	problem smallint)");
if (!$result) {
	writeLog("An error occurred during Table creation.");
	exit;
}

writeLog("Populating main table..");	
$sql = "INSERT INTO $tempTableName SELECT DISTINCT ON (net, radio, area, mcc, cell) radio, mcc, net, area, cell, unit,
	ST_SetSRID(ST_MakePoint(lon, lat), 4326) As pos,
	range, samples, changeable, created, updated, averagesignal, 0 As problem
	FROM $tempImportName";
$result = pg_query($conn, $sql);	
if (!$result) {
	writeLog("Error populating main table.");
	exit;
}

include "countryDbBuilder.php";

writeLog("Checking for cells outside of their country..");	
$sql = "UPDATE $tempTableName t1 SET problem = 1 WHERE NOT (pos && (SELECT outline FROM mcc t2 WHERE t2.mcc = t1.mcc))";
$result = pg_query($conn, $sql);	
if (!$result) {
	writeLog("Error checking cells.");
	exit;
}
$rowsOutsideMcc = pg_affected_rows($result);
writeLog("$rowsOutsideMcc rows affected.");

writeLog("Creating primary key..");
$tempTablePKey = $tempTableName . "_pkey";

$sql = "ALTER TABLE $tempTableName ADD CONSTRAINT $tempTablePKey PRIMARY KEY (net, radio, area, mcc, cell)";
$result = pg_query($conn, $sql);
if (!$result) {
	writeLog("An error occurred during primary key creation.");
	exit;
}

writeLog("Creating indexes..");
$sql = "CREATE INDEX $tempGixName ON $tempTableName USING GIST (pos)";
$result = pg_query($conn, $sql);
if (!$result) {
	writeLog("Couldn't create $tempGixName.");
	exit;
}
/*

writeLog("Clustering spatial index..");
$sql = "CLUSTER $tempTableName USING $tempGixName;";
$result = pg_query($conn, $sql);
if (!$result) {
	writeLog("Couldn't cluster $tempGixName.");
	exit;
}*/

pg_query($conn, "ALTER TABLE $tempTableName SET LOGGED");

writeLog("Analyzing $tempTableName Table..");
$sql = "ANALYZE $tempTableName;";
$result = pg_query($conn, $sql);
if (!$result) {
	writeLog("Couldn't analyze Table.");
	exit;
}

writeLog("Creating LAC Table..");
$sql = "CREATE TABLE $tempLacTableName(
	radio text,
	mcc smallint, 
	net smallint,
	area integer,
	cPos geometry(POINT, 4326),
	outline geometry(GEOMETRY, 4326),
	size integer,
	invalidCells integer,
	tempDist real)";
$result = pg_query($conn, $sql);
if (!$result) {
	writeLog("Couldn't create LAC Table.");
	exit;
}

writeLog("Populating LAC Table..");
$sql = "INSERT INTO $tempLacTableName SELECT radio, mcc, net, area, 
	null AS cPos,
	null AS outline,
	COUNT(*) AS size
	FROM $tempTableName
	GROUP BY area, radio, net, mcc";
$result = pg_query($conn, $sql);	
if (!$result) {
	writeLog("Error populating LAC Database.");
	exit;
}

writeLog("Creating primary key..");
$tempLacTablePKey = $tempLacTableName . "_pkey";

$result = pg_query($conn, "ALTER TABLE $tempLacTableName
	ADD CONSTRAINT $tempLacTablePKey PRIMARY KEY (radio, net, area, mcc)");
if (!$result) {
	writeLog("An error occurred during primary key creation.");
	exit;
}

writeLog("Computing LAC center points..");
// ST_GeometricMedian much better than ST_Centroid. Needs PostGIS 2.3
$sql = "UPDATE $tempLacTableName t1 SET cPos = ST_Centroid(ST_COLLECT(ARRAY(SELECT t2.pos FROM $tempTableName t2 WHERE t2.area = t1.area AND t2.mcc = t1.mcc AND t2.net = t1.net AND t2.radio = t1.radio AND problem = 0)))";
$result = pg_query($conn, $sql);	
if (!$result) {
	writeLog("Error Computing LAC centers.");
	exit;
}

writeLog("Computing Cell distance to LAC center..");
$sql = "UPDATE $tempLacTableName t1 SET tempDist = (SELECT avg(ST_Distance(t2.pos, t1.cPos)) FROM $tempTableName t2 WHERE t2.area = t1.area AND t2.mcc = t1.mcc AND t2.net = t1.net AND t2.radio = t1.radio)";
$result = pg_query($conn, $sql);	
if (!$result) {
	writeLog("Error Computing LAC distances.");
	exit;
}

writeLog("Checking LAC cells for outliers..");
$sql = "UPDATE $tempTableName t1 SET problem = 2 WHERE ST_Distance(pos, (SELECT cPos FROM $tempLacTableName t2 WHERE t2.area = t1.area AND t2.mcc = t1.mcc AND t2.net = t1.net AND t2.radio = t1.radio))
														> ($paraMaxAvgDistanceRatio * (SELECT tempDist FROM $tempLacTableName t2 WHERE t2.area = t1.area AND t2.mcc = t1.mcc AND t2.net = t1.net AND t2.radio = t1.radio))";
$result = pg_query($conn, $sql);	
if (!$result) {
	writeLog("Error checking cells.");
	exit;
}
$rowsFarFromArea = pg_affected_rows($result);
writeLog("$rowsFarFromArea rows affected.");


writeLog("Computing LAC geometry..");
$sql = "UPDATE $tempLacTableName t1 SET outline = ST_NULLABLECONCAVEHULL(ST_COLLECT(ARRAY(SELECT t2.pos FROM $tempTableName t2 WHERE t2.area = t1.area AND t2.mcc = t1.mcc AND t2.net = t1.net AND t2.radio = t1.radio AND problem = 0)), 0.99)";
$result = pg_query($conn, $sql);	
if (!$result) {
	writeLog("Error Computing LAC geometry.");
	exit;
}

writeLog("Counting invalid cells of each LAC..");
$sql = "UPDATE $tempLacTableName t1 SET invalidCells = (SELECT Count(*) FROM $tempTableName t2 WHERE t2.area = t1.area AND t2.mcc = t1.mcc AND t2.net = t1.net AND t2.radio = t1.radio AND problem <> 0)";
$result = pg_query($conn, $sql);	
if (!$result) {
	writeLog("Error Counting cells.");
	exit;
}

writeLog("Droping distance column..");
$sql = "ALTER TABLE $tempLacTableName DROP COLUMN tempDist";
$result = pg_query($conn, $sql);
if (!$result) {
	writeLog("Couldn't drop column.");
	exit;
}

writeLog("Creating LAC index..");
$sql = "CREATE INDEX $tempLacGixName ON $tempLacTableName USING GIST (cPos)";
$result = pg_query($conn, $sql);
if (!$result) {
	writeLog("Couldn't create $tempLacGixName.");
	exit;
}

writeLog("Clustering spatial index..");
$sql = "CLUSTER $tempLacTableName USING $tempLacGixName";
$result = pg_query($conn, $sql);
if (!$result) {
	writeLog("Couldn't cluster $tempLacGixName.");
	exit;
}

writeLog("Analyzing $tempLacTableName..");
$sql = "ANALYZE $tempLacTableName";
$result = pg_query($conn, $sql);	
if (!$result) {
	writeLog("Couldn't analyze Table.");
	exit;
}

$problematicCells = $rowsOutsideMcc + $rowsFarFromArea;
writeLog("Populating info table..");
$sql = "INSERT INTO $generalInfoTableName VALUES ('$infoParam', CURRENT_TIMESTAMP, '$srcFileName', $problematicCells, null)
	 ON CONFLICT (para) DO UPDATE SET time = CURRENT_TIMESTAMP, sInfo = '$srcFileName', iInfo = $problematicCells, eInfo = null";
$result = pg_query($conn, $sql);	
if (!$result) {
	writeLog("Couldn't create Builddate Entry.");
	exit;
}

writeLog("Renaming Tables..");
pg_query($conn, "DROP TABLE IF EXISTS $tempImportName");
pg_query($conn, "DROP TABLE IF EXISTS $finalTableName");
pg_query($conn, "DROP TABLE IF EXISTS $finalLacTableName");

pg_query($conn, "ALTER TABLE $tempTableName RENAME TO $finalTableName");
pg_query($conn, "ALTER TABLE $tempLacTableName RENAME TO $finalLacTableName");

pg_query($conn, "ALTER INDEX {$tempTableName}_pkey RENAME TO {$finalTableName}_pkey");
pg_query($conn, "ALTER INDEX {$tempLacTableName}_pkey RENAME TO {$finalLacTableName}_pkey");

pg_query($conn, "ALTER INDEX $tempGixName RENAME TO $finalGixName");
pg_query($conn, "ALTER INDEX $tempLacGixName RENAME TO $finalLacGixName");


pg_close($conn);

$mtime = microtime(); 
$mtime = explode(" ",$mtime); 
$mtime = $mtime[1] + $mtime[0]; 
$endtime = $mtime; 
$totaltime = ($endtime - $starttime); 
writeLog("Done. Took $totaltime seconds.");

if($argv[1] == "ocid")
	include "sMeasDbBuilder.php";
?>
