<?php
/* Copyright (C) 2016  Lehrstuhl für Technische Elektronik, Friedrich-Alexander-Universität Erlangen-Nürnberg */
/* https://github.com/lte-fau/MLS-Map/blob/master/LICENSE */
//include "secure.php";
include "db-settings.php";
include "logHelper.php";

//__________Params___________
$mode = 1; 						// Mode0 -> Merge last_measurements, Mode1 -> Create Database from local files, Mode2 -> Both
$startingFileIndex = 1;			// First local filenumber witch to import
$endingFileIndex = 2;			// Last local filenumber witch to import
$dropExistingData = 0;		
$localFileName = "tmp/measurements_"; // A number and .csv.gz will be added later

$LACs = array(14794, 17441, 17442, 17443, 17445, 17445, 17446, 17449, 17450, 17450, 17481, 17481, 17481, 37896, 37897, 38149, 38157, 50833, 51802, 52857, 65402, 22509, 22565, 302, 527, 577, 810, 810, 810, 927, 955, 1090, 1092, 2401, 7056, 7984, 8208, 8208, 8209, 9217, 9257, 9654, 9676, 9710, 11421, 17440, 17441, 17443, 17443, 17443, 17444, 17444, 17445, 17447, 17447, 17447, 17447, 17449, 17449, 17450, 17450, 17473, 17473, 17474, 17474, 17586, 29889, 32057, 34858, 37399, 37890, 37893, 37899, 37900, 38175, 40227, 47971, 50830, 50831, 50831, 50831, 50833, 50833, 50833, 51814, 52857, 52858, 52863, 65382, 9108, 12940, 65322, 22511, 831, 988, 2832, 4424, 8208, 8209, 9117, 9117, 9217, 9217, 9262, 9745, 10755, 17438, 17440, 17441, 17441, 17442, 17442, 17444, 17444, 17449, 17474, 17474, 17596, 29024, 37891, 37892, 38150, 38176, 50830, 50830, 50830, 50831, 52858, 65287, 65296, 65374, 65423, 34858, 37894, 38180, 40017, 40237, 50840, 52863, 65372, 21657, 577, 600, 3644, 3652, 3656, 8208, 8209, 8688, 8695, 9117, 9117, 9127, 9127, 9237, 9237, 9257, 9630, 9684, 17440, 17440, 17442, 17446, 17446, 17446, 17473, 17473, 17481, 42518, 46737, 50840);
$MNCs = array(1, 1, 1, 3, 1, 3, 7, 2, 1, 3, 2, 3, 7, 1, 1, 1, 1, 3, 7, 7, 7, 1, 1, 3, 3, 3, 1, 2, 7, 3, 2, 1, 1, 3, 10, 3, 1, 2, 7, 2, 3, 3, 3, 3, 7, 3, 2, 1, 2, 7, 1, 7, 2, 1, 2, 3, 7, 1, 3, 2, 7, 1, 2, 1, 2, 1, 1, 1, 1, 3, 1, 1, 1, 1, 1, 3, 1, 2, 1, 2, 7, 1, 2, 7, 7, 3, 3, 7, 2, 3, 1, 1, 1, 3, 2, 3, 1, 7, 1, 1, 3, 3, 7, 3, 3, 2, 1, 2, 3, 7, 2, 7, 2, 3, 7, 3, 7, 1, 1, 1, 1, 1, 1, 1, 3, 7, 3, 7, 3, 2, 1, 7, 7, 1, 1, 3, 3, 7, 3, 2, 1, 1, 7, 1, 1, 1, 3, 2, 2, 2, 2, 7, 2, 3, 1, 3, 2, 3, 3, 1, 7, 3, 1, 2, 3, 3, 7, 1, 1, 7, 3);

$tempTableName = "tempmeas";
$generalTableName = "gInfo";

$infoParam = "MEAS_UPDATE_DATE";

$dataURL = "http://opencellid.org/downloads/?apiKey=" . $ocidAPIKey . "&filename=last_measurements.csv.gz";
//___________________________


// 1. Drop existing Data if set
// 2. Extract file
// 3. Import into temp table
// 4. Update Cell table with data from temptable
// 5. Drop temp table

$mtime = microtime(); 
$mtime = explode(" ",$mtime); 
$mtime = $mtime[1] + $mtime[0]; 
$starttime = $mtime; 

writeLog("<br>*******************************************\n");
writeLog("* Starting dbBuilder for OCIDMeasurements *\n");
writeLog("********* Area selective, Mode = $mode *********\n");
writeLog("*******************************************\n<br>");

$num = count($LACs);

writeLog("Found $num Location Area Codes..\n");

// Create connection
$conn = pg_connect($connString . " sslmode=disable")
	or die('Could not connect: ' . pg_last_error());

writeLog("Connected to database successfully.\n");

// Create meas column in cell Table if not exists
writeLog("Checking cell Table measurecolumn..\n");
$sql = "SELECT column_name FROM information_schema.columns WHERE table_name='ocid' and column_name='meas'";
$result = pg_query($conn, $sql);	
if (!$result) {
	writeLog("Coudn't check column.\n");
	exit;
}

if(pg_num_rows($result) == 0)
{
	// Column doesn't exist
	$result = pg_query($conn, "ALTER TABLE ocid ADD COLUMN meas text");
	if (!$result) {
		exit;
	}
}else if($dropExistingData == 1)
{
	writeLog("Droping old Data..\n");
	$sql = "UPDATE ocid SET meas = ''";
	$result = pg_query($conn, $sql);	
	if (!$result) {
		writeLog("Couldn't drop old Data.\n");
		exit;
	}
}

if($mode == 1 || $mode == 2)
{
	$fileIndex = $startingFileIndex;
	for($fileIndex; $fileIndex <= $endingFileIndex; $fileIndex++)
	{
		$fileName = $localFileName . $fileIndex . ".csv.gz";
		writeLog("*** Importing local file $fileName..\n");
		writeLog("Extracting file..\n");
		
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
		
		writeLog("Creating temp table..\n");
		pg_query($conn, "DROP TABLE IF EXISTS $tempTableName");

		// COPY is fastest wenn done in the same transaction as CREATE TABLE
		pg_query($conn, "BEGIN");

		$result = pg_query($conn, "CREATE TABLE $tempTableName(
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
			writeLog("An error occurred during Table creation.\n");
			exit;
		}

		writeLog("Importing Data..\n");
		$result = pg_query($conn, "SELECT import_csv_file_to_table('$tempTableName', '$srcFileName')");
		if (!$result) {
			writeLog("An error occurred during Bulk import.\n");
			exit;
		}
		pg_query($conn, "COMMIT");
		
		// Delete unwated entries
		writeLog("Deleting unwated entries..\n");
		
		$lacStr = join("','",$LACs);   
		$mncStr = join("','",$MNCs);   
		
		//$sql = "DELETE FROM $tempTableName WHERE area NOT IN ('$lacStr') OR mcc <> 262 OR radio <> 'GSM'";
		$sql = "DELETE FROM $tempTableName WHERE lon < 10.728836059570312 OR lon > 11.40380859375 OR lat < 49.26197951930051 OR lat > 49.685401019041414";
		$result = pg_query($conn, $sql);	
		if (!$result) {
			writeLog("Coudn't delete entries.\n");
			exit;
		}
		
		// Merge with cellTable
		writeLog("Merging data into cellTable..\n");
		$sql = "UPDATE ocid t1 SET meas = meas || (SELECT string_agg(to_char(lon, '999D999999999') || ';' || to_char(lat, '999D999999999'), '|') FROM $tempTableName t2 WHERE t1. mcc = t2.mcc AND t1.net = t2.net AND t1.area = t2.area AND t1.cell = t2.cell) || '&&' 
				WHERE t1.area IN ('$lacStr') AND mcc = 262 AND radio = 'GSM'";
		$result = pg_query($conn, $sql);	
		if (!$result) {
			writeLog("Couldn't merge Data.\n");
			exit;
		}
		
		pg_query($conn, "DROP TABLE IF EXISTS $tempTableName");
	}
}

if($mode == 0 || $mode == 2)
{
	writeLog("Downloading datafile..\n");
	file_put_contents($fileName, fopen("$dataURL", 'r'));
	
	// TBC
}

writeLog("Creating info table..\n");
$result = pg_query($conn, "CREATE TABLE IF NOT EXISTS $generalTableName(
	para char(50) NOT NULL, 
	time timestamp, 
	sInfo char(50),
	iInfo integer,
	eInfo integer,
	PRIMARY KEY (para))");

if (!$result) {
	writeLog("An error occurred during general Table creation.\n");
	exit;
}

writeLog("Populating info table..\n");
$sql = "INSERT INTO $generalTableName VALUES ('$infoParam', CURRENT_TIMESTAMP, '$srcFileName', null, null)
	 ON CONFLICT (para) DO UPDATE SET time = CURRENT_TIMESTAMP, sInfo = '$startingFileIndex - $endingFileIndex', iInfo = $mode, eInfo = null";
$result = pg_query($conn, $sql);	
if (!$result) {
	writeLog("Couldn't create Builddate Entry.\n");
	exit;
}

pg_close($conn);

$mtime = microtime(); 
$mtime = explode(" ",$mtime); 
$mtime = $mtime[1] + $mtime[0]; 
$endtime = $mtime; 
$totaltime = ($endtime - $starttime); 
writeLog("Done. Took $totaltime seconds.\n");
?>
