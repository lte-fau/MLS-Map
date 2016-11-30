<?php
/* Copyright (C) 2016  Lehrstuhl für Technische Elektronik, Friedrich-Alexander-Universität Erlangen-Nürnberg */
/* https://github.com/lte-fau/MLS-Map/blob/master/LICENSE */
//include "secure.php";
include "db-settings.php";

//__________Params___________
$mergeData = 1;					// 0 -> Drops old Database and creates a new one
$mode = 1; 						// Mode0 -> Download last_measurements, Mode1 -> Load local file
// --
$localFileName = "tmp/measurements_2.csv.gz";
// --
$fileName = "tmp/OCID_Measurements.csv.gz";

$tempTableName = "tempmeas";

$finalTableName = "ocidMeas";
$generalTableName = "gInfo";

$infoParam = "MEAS_UPDATE_DATE";

$dataURL = "http://opencellid.org/downloads/?apiKey=" . $ocidAPIKey . "&filename=last_measurements.csv.gz";
//___________________________

$mtime = microtime(); 
$mtime = explode(" ",$mtime); 
$mtime = $mtime[1] + $mtime[0]; 
$starttime = $mtime; 

$logfilename = "tmp/log.txt";

file_put_contents($logfilename, "<br>*******************************************\n", FILE_APPEND);
file_put_contents($logfilename, "* Starting dbBuilder for OCIDMeasurements *\n", FILE_APPEND);
file_put_contents($logfilename, "*******************************************\n<br>", FILE_APPEND);

if($mode == 0)
{
	file_put_contents($logfilename, date("[Y-m-d H:i:s] ") . "Downloading datafile..\n", FILE_APPEND);
	echo "Downloading datafile..\n";
	file_put_contents($fileName, fopen("$dataURL", 'r'));
} else
	$fileName = $localFileName;

file_put_contents($logfilename, date("[Y-m-d H:i:s] ") . "Extracting file..\n", FILE_APPEND);
echo "Extracting file..\n";
$buffer_size = 1048576; // 1MiB
$outputFileName = str_replace('.gz', '', $fileName); 
$srcFileName = str_replace('tmp/', '', $outputFileName); 

$file = gzopen($fileName, 'rb');
$outputFile = fopen($outputFileName, 'wb'); 

if($file == false || $outputFile == false)
	exit;

while (!gzeof($file)) {
    fwrite($outputFile, gzread($file, $buffer_size));
}

fclose($outputFile);
gzclose($file);

file_put_contents($logfilename, date("[Y-m-d H:i:s] ") . "Deleting downloaded file..\n", FILE_APPEND);
echo "Deleting downloaded file..\n";

// Only delete downloaded file
if($mode == 0)
	unlink($fileName);

file_put_contents($logfilename, date("[Y-m-d H:i:s] ") . "All done! Starting MeasDbBuilder..\n", FILE_APPEND);
echo "All done! Starting MeasDbBuilder..\n";

// Create connection
$conn = pg_connect($connString . " sslmode=disable")
	or die('Could not connect: ' . pg_last_error());

file_put_contents($logfilename, date("[Y-m-d H:i:s] ") . "Connected to database successfully.\n", FILE_APPEND);
echo "Connected to database successfully.\n";

file_put_contents($logfilename, date("[Y-m-d H:i:s] ") . "Creating temp table..\n", FILE_APPEND);
echo "Creating temp table..\n";

pg_query($conn, "DROP TABLE IF EXISTS $tempTableName");

// COPY is fastest wenn done in the same transaction as CREATE TABLE
pg_query($conn, "BEGIN");

$result = pg_query($conn, "CREATE TEMPORARY TABLE $tempTableName(
	mcc smallint, 
	net smallint,
	area integer,
	cell integer,
	lon double precision,
	lat double precision,
	signal smallint,
	measured bigint,
	created bigint,
	rating real,
	speed real,
	direction real,
	radio text,
	ta integer,
	rnc integer,
	cid integer,
	psc integer,
	tac integer,
	pci integer,
	sid integer,
	nid integer,
	bid integer)");
if (!$result) {
	file_put_contents($logfilename, date("[Y-m-d H:i:s] ") . "An error occurred during Table creation.\n", FILE_APPEND);
	echo "An error occurred during Table creation.\n";
	exit;
}


echo "Importing Data..\n";
$result = pg_query($conn, "SELECT import_csv_file_to_table('$tempTableName', '$srcFileName')");
if (!$result) {
	file_put_contents($logfilename, date("[Y-m-d H:i:s] ") . "An error occurred during Bulk import.\n", FILE_APPEND);
	echo "An error occurred during Bulk import.\n";
	exit;
}
pg_query($conn, "COMMIT");

// Add pos
$result = pg_query($conn, "ALTER TABLE $tempTableName ADD COLUMN pos geometry(POINT, 4326)");
if (!$result) {
	file_put_contents($logfilename, date("[Y-m-d H:i:s] ") . "An error occurred during Table modification.\n", FILE_APPEND);
	echo "An error occurred during Table modification.\n";
	exit;
}

// compute pos
file_put_contents($logfilename, date("[Y-m-d H:i:s] ") . "Computing position..\n", FILE_APPEND);
echo "Computing position..\n";		
$sql = "UPDATE $tempTableName SET pos = ST_SetSRID(ST_MakePoint(lon, lat), 4326)";
$result = pg_query($conn, $sql);	
if (!$result) {
	file_put_contents($logfilename, date("[Y-m-d H:i:s] ") . "Error creating geometry!.\n", FILE_APPEND);
	echo "Error creating geometry!.\n";
	exit;
}

// drop lon, lat

file_put_contents($logfilename, date("[Y-m-d H:i:s] ") . "Droping lon, lat column..\n", FILE_APPEND);
echo "Droping lon, lat column..\n";
$sql = "ALTER TABLE $tempTableName DROP COLUMN lon, DROP COLUMN lat";
$result = pg_query($conn, $sql);
if (!$result) {
	file_put_contents($logfilename, date("[Y-m-d H:i:s] ") . "Couldn't drop columns.\n", FILE_APPEND);
	echo "Couldn't drop column.\n";
	exit;
}

file_put_contents($logfilename, date("[Y-m-d H:i:s] ") . "Creating new main Table..\n", FILE_APPEND);
echo "Creating new main Table..\n";
if($mergeData == 0)
{
	pg_query($conn, "DROP TABLE IF EXISTS $finalTableName");
	
	$result = pg_query($conn, "CREATE TABLE $finalTableName(
	mcc smallint, 
	net smallint,
	area integer,
	cell integer,
	signal smallint,
	measured bigint,
	created bigint,
	rating real,
	speed real,
	direction real,
	radio text,
	ta integer,
	rnc integer,
	cid integer,
	psc integer,
	tac integer,
	pci integer,
	sid integer,
	nid integer,
	bid integer,
	pos geometry(POINT, 4326))");
	if (!$result) {
		file_put_contents($logfilename, date("[Y-m-d H:i:s] ") . "An error occurred during main Table creation.\n", FILE_APPEND);
		echo "An error occurred during main Table creation.\n";
		exit;
	}
}

// Merge with main Table
file_put_contents($logfilename, date("[Y-m-d H:i:s] ") . "Droping Index of main table..\n", FILE_APPEND);
echo "Droping Index of main table..\n";
$sql = "DROP INDEX IF EXISTS {$finalTableName}_idx";
$result = pg_query($conn, $sql);
if (!$result) {
	file_put_contents($logfilename, date("[Y-m-d H:i:s] ") . "Couldn't drop index.\n", FILE_APPEND);
	echo "Couldn't drop index.\n";
	exit;
}


file_put_contents($logfilename, date("[Y-m-d H:i:s] ") . "Merging new Data..\n", FILE_APPEND);
echo "Merging new Data..\n";
$sql = "INSERT INTO $finalTableName SELECT * FROM $tempTableName";
$result = pg_query($conn, $sql);
if (!$result) {
	file_put_contents($logfilename, date("[Y-m-d H:i:s] ") . "Error while merging.\n", FILE_APPEND);
	echo "Error while merging.\n";
	exit;
}

pg_query($conn, "DROP TABLE IF EXISTS $tempTableName");

file_put_contents($logfilename, date("[Y-m-d H:i:s] ") . "Creating indexes..\n", FILE_APPEND);
echo "Creating indexes..\n";
$sql = "CREATE INDEX {$finalTableName}_idx ON $finalTableName (radio, mcc, net, area, cell)";
$result = pg_query($conn, $sql);
if (!$result) {
	file_put_contents($logfilename, date("[Y-m-d H:i:s] ") . "Couldn't create indexes.\n", FILE_APPEND);
	echo "Couldn't create indexes.\n";
	exit;
}

file_put_contents($logfilename, date("[Y-m-d H:i:s] ") . "Analyzing $finalTableName Table..\n", FILE_APPEND);
echo "Analyzing $finalTableName Table..\n";
$sql = "ANALYZE $finalTableName;";
$result = pg_query($conn, $sql);
if (!$result) {
	file_put_contents($logfilename, date("[Y-m-d H:i:s] ") . "Couldn't analyze Table.\n", FILE_APPEND);
	echo "Couldn't analyze Table.\n";
	exit;
}


file_put_contents($logfilename, date("[Y-m-d H:i:s] ") . "Creating info table..\n", FILE_APPEND);
echo "Creating info table..\n";
$result = pg_query($conn, "CREATE TABLE IF NOT EXISTS $generalTableName(
	para text NOT NULL, 
	time timestamp, 
	sInfo text,
	iInfo integer,
	eInfo integer,
	PRIMARY KEY (para))");

if (!$result) {
	file_put_contents($logfilename, date("[Y-m-d H:i:s] ") . "An error occurred during general Table creation.\n", FILE_APPEND);
	echo "An error occurred during general Table creation.\n";
	exit;
}


file_put_contents($logfilename, date("[Y-m-d H:i:s] ") . "Populating info table..\n", FILE_APPEND);
echo "Populating info table..\n";
$sql = "INSERT INTO $generalTableName VALUES ('$infoParam', CURRENT_TIMESTAMP, '$srcFileName', null, null)
	 ON CONFLICT (para) DO UPDATE SET time = CURRENT_TIMESTAMP, sInfo = '$srcFileName', iInfo = null, eInfo = null";
$result = pg_query($conn, $sql);	
if (!$result) {
	file_put_contents($logfilename, date("[Y-m-d H:i:s] ") . "Couldn't create Builddate Entry.\n", FILE_APPEND);
	echo "Couldn't create Builddate Entry.\n";
	exit;
}

pg_close($conn);

$mtime = microtime(); 
$mtime = explode(" ",$mtime); 
$mtime = $mtime[1] + $mtime[0]; 
$endtime = $mtime; 
$totaltime = ($endtime - $starttime); 
file_put_contents($logfilename, date("[Y-m-d H:i:s] ") . "Done. Took " . $totaltime . " seconds.\n", FILE_APPEND);
echo "Done. Took " . $totaltime . " seconds.\n"; 
?>
