<?php
/* Copyright (C) 2016  Lehrstuhl für Technische Elektronik, Friedrich-Alexander-Universität Erlangen-Nürnberg */
/* https://github.com/lte-fau/MLS-Map/blob/master/LICENSE */
include "local.php";
include "db-settings.php";
include "logHelper.php";

//__________Params___________
$mode = 1;								// Mode0 -> Merge last_measurements, Mode1 -> Create Database from local files, Mode2 -> Both
$startingFileIndex = 1;					// First local filenumber witch to import
$endingFileIndex = 15;					// Last local filenumber witch to import
$dropExistingData = 1;					// Only dropped if importing local files
$localFileName = "tmp/measurements_";	// A number and .csv.gz will be added later

$filterMode = 1;						// 0 -> No filtering, 1 -> Filter by Cords, 2 -> Filter by MCC
$filterMcc = 262;
$UpperLonLimit = 11.9;
$LowerLonLimit = 10.3;
$UpperLatLimit = 50.2;
$LowerLatLimit = 48.6;

// Importing last_measurements multiple times results in the same measurements showing up multiple times. Last measurement flag? Or use only numbered files (-> One month old data)

$tempTableName = "tempmeas";
$generalTableName = "gInfo";

$infoParam = "MEAS_UPDATE_DATE";

$dataURL = "http://opencellid.org/downloads/?apiKey=" . $ocidAPIKey . "&filename=last_measurements.csv.gz";
//___________________________

$mtime = microtime();
$mtime = explode(" ",$mtime);
$mtime = $mtime[1] + $mtime[0];
$starttime = $mtime;

writeLog("");
writeLog("*******************************************");
writeLog("* Starting dbBuilder for OCIDMeasurements *");
writeLog("******** Area selective, Mode = $mode *********");
writeLog("*******************************************");
writeLog("");

// Create connection
$conn = pg_connect($connString . " sslmode=disable")
	or die('Could not connect: ' . pg_last_error());
writeLog("Connected to database successfully.");

if($mode == 0 || $mode == 2)
	$endingFileIndex++;
if($mode == 0)
	$startingFileIndex = $endingFileIndex;

if($mode == 1 || $mode == 2)
{
	if($dropExistingData == 1)
	{
		writeLog("Droping old Data..");
		$sql = "ALTER TABLE ocid DROP COLUMN IF EXISTS meas";
		$result = pg_query($conn, $sql);	
		if (!$result) {
			writeLog("Couldn't drop old Data.");
			exit;
		}
	}	
}

writeLog("Checking cell Table measure column..");  

$sql = "SELECT column_name FROM information_schema.columns WHERE table_name='ocid' and column_name='meas'";
$result = pg_query($conn, $sql);	
if (!$result) {
	writeLog("Coudn't check column.");
	exit;
}

if(pg_num_rows($result) == 0)
{
	// Column doesn't exist
	$sql = "ALTER TABLE ocid ADD COLUMN meas geometry(MULTIPOINTZ, 4326)";
	$result = pg_query($conn, $sql);
	if (!$result) {
		exit;
	}
}
	
$fileIndex = $startingFileIndex;
for($fileIndex; $fileIndex <= $endingFileIndex; $fileIndex++)
{
	// Loop through all files that should be imported
	// extract, import, delete extracted file
	
	if(($mode == 0 || $mode == 2) && $fileIndex == $endingFileIndex)
	{
		$fileName = "tmp/" . "last_measurements.csv.gz";
		writeLog("*** Importing remote file $fileName..");
		writeLog("Downloading datafile..");
		file_put_contents($fileName, fopen("$dataURL", 'r'));
	}else 
	{
		$fileName = $localFileName . $fileIndex . ".csv.gz";
		writeLog("*** Importing local file $fileName..");
	}

	writeLog("Extracting file..");
	
	$buffer_size = 10485760; // 10MiB
	$outputFileName = str_replace('.gz', '', $fileName); 
	$srcFileName = str_replace('tmp/', '', $outputFileName); 

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
	
	writeLog("Creating temp table..");
	pg_query($conn, "DROP TABLE IF EXISTS $tempTableName");

	// COPY is fastest wenn done in the same transaction as CREATE TABLE
	pg_query($conn, "BEGIN");
	$sql = "CREATE TABLE $tempTableName(
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
				bid integer)";
	$result = pg_query($conn, $sql);
	if (!$result) {
		writeLog("An error occurred during Table creation.");
		exit;
	}

	writeLog("Importing Data..");
	$sql = "SELECT import_csv_file_to_table('$tempTableName', '$srcFileName')";
	$result = pg_query($conn, $sql);
	if (!$result) {
		writeLog("An error occurred during Bulk import.");
		exit;
	}
	pg_query($conn, "COMMIT");
	
	// Delete unpacked file
	unlink("tmp/" . $srcFileName);
	
	if($filterMode != 0)
	{
		writeLog("Deleting unwated entries.."); 
		if($filterMode == 1)
			$sql = "DELETE FROM $tempTableName WHERE lon < $LowerLonLimit OR lon > $UpperLonLimit OR lat < $LowerLatLimit OR lat > $UpperLatLimit";
		else
			$sql = "DELETE FROM $tempTableName WHERE mcc <> $filterMcc";
		$result = pg_query($conn, $sql);	
		if (!$result) {
			writeLog("Coudn't delete entries.");
			exit;
		}
	}
	
	writeLog("Merging data into cellTable..");		
	$sql = "UPDATE ocid t1 SET meas = ST_Multi(ST_CollectionHomogenize(ST_Collect(meas, atm.cMeas)))
				FROM (SELECT ST_Collect(ST_SetSRID(ST_MakePoint(lon, lat, signal), 4326)) AS cMeas, mcc, net, area, cell, radio FROM $tempTableName GROUP BY mcc, net, area, cell, radio) atm
				WHERE t1.mcc = atm.mcc AND t1.net = atm.net AND t1.area = atm.area AND t1.cell = atm.cell AND t1.radio = atm.radio";
	$result = pg_query($conn, $sql);
	if (!$result) {
		writeLog("Couldn't merge Data.");
		exit;
	}

	pg_query($conn, "DROP TABLE IF EXISTS $tempTableName");
}

writeLog("Creating info table..");
$sql = "CREATE TABLE IF NOT EXISTS $generalTableName(
			para text NOT NULL,
			time timestamp,
			sInfo text,
			iInfo integer,
			eInfo integer,
			PRIMARY KEY (para))";
$result = pg_query($conn, $sql);
if (!$result) {
	writeLog("An error occurred during general Table creation.");
	exit;
}

writeLog("Populating info table..");
$sql = "INSERT INTO $generalTableName VALUES ('$infoParam', CURRENT_TIMESTAMP, '$srcFileName', null, null)
		ON CONFLICT (para) DO UPDATE SET time = CURRENT_TIMESTAMP, sInfo = '$startingFileIndex - $endingFileIndex', iInfo = $mode, eInfo = null";
$result = pg_query($conn, $sql);	
if (!$result) {
	writeLog("Couldn't create Builddate Entry.");
	exit;
}

pg_close($conn);

$mtime = microtime(); 
$mtime = explode(" ",$mtime); 
$mtime = $mtime[1] + $mtime[0]; 
$endtime = $mtime; 
$totaltime = ($endtime - $starttime); 
writeLog("Done. Took $totaltime seconds.");
?>
