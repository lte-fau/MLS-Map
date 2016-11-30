<?php
/* Copyright (C) 2016  Lehrstuhl für Technische Elektronik, Friedrich-Alexander-Universität Erlangen-Nürnberg */
/* https://github.com/lte-fau/MLS-Map/blob/master/LICENSE */
include "local.php";
include "db-settings.php";
include "logHelper.php";

if($argv[1] == "ocid")
{
	//__________Params___________
	$fileName = "tmp/OCIDTowers.csv.gz";

	$tempImportName = "bulkcells";
	$tempTableName = "tempCells";
	$tempLacTableName = "tempLACs";

	$tempGixName = "temp_gix";
	$tempLacGixName = "temp_lac_gix";

	$finalTableName = "ocid";
	$finalLacTableName = "ocidLACs";
	$generalTableName = "gInfo";

	$finalGixName = "ocid_gix";
	$finalLacGixName = "ocid_lac_gix";

	$infoParam = "OCID_BUILD_DATE";
	
	$dataURL = "http://opencellid.org/downloads/?apiKey=" . $ocidAPIKey . "&filename=cell_towers.csv.gz";
	//___________________________
} else if($argv[1] == "mls")
{
	//__________Params___________
	$fileName = "MLSTowers.csv.gz";

	$tempImportName = "bulkcells";
	$tempTableName = "tempCells";
	$tempLacTableName = "tempLACs";

	$tempGixName = "temp_gix";
	$tempLacGixName = "temp_lac_gix";

	$finalTableName = "mls";
	$finalLacTableName = "mlsLACs";
	$generalTableName = "gInfo";

	$finalGixName = "mls_gix";
	$finalLacGixName = "mls_lac_gix";

	$infoParam = "MLS_BUILD_DATE";
	
	$dataURL = $argv[2];
	//___________________________
}else{
	exit;
}

$mtime = microtime(); 
$mtime = explode(" ",$mtime); 
$mtime = $mtime[1] + $mtime[0]; 
$starttime = $mtime; 

writeLog("<br>*******************************************\n");
writeLog("******* Starting dbBuilder for $argv[1] *******\n");
writeLog("*******************************************\n<br>");

writeLog("Downloading datafile..\n");
file_put_contents($fileName, fopen("$dataURL", 'r'));

writeLog("Extracting file..\n");
$buffer_size = 1048576; // 1MiB
$outputFileName = str_replace('.gz', '', $fileName); 
$srcFileName = $outputFileName;

$file = gzopen($fileName, 'rb');
$outputFile = fopen($outputFileName, 'wb'); 

if($file == false || $outputFile == false)
	exit;

while (!gzeof($file)) {
    fwrite($outputFile, gzread($file, $buffer_size));
}

fclose($outputFile);
gzclose($file);

if($argv[1] == "mls")
{
	writeLog("Removing carriage return characters..\n");
	
	$file_read = fopen($outputFileName, "r");
	$outputFileName = str_replace('tmp/', '', $outputFileName); 
	$outputFileName = "Mod" . $outputFileName;
	$file_write = fopen($outputFileName, "w+");
	
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

writeLog("All done! Starting DbBuilder..\n");

// Create connection
$conn = pg_connect($connString . " sslmode=disable")
	or die('Could not connect: ' . pg_last_error());

writeLog("Connected to database successfully.\n");

writeLog("Creating bulk-import table..\n");
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
	writeLog("An error occurred during Table creation.\n");
	exit;
}

writeLog("Importing Data..\n");
$result = pg_query($conn, "SELECT import_csv_file_to_table('$tempImportName', '$srcFileName')");
if (!$result) {
	writeLog("Importing Data..\n");
	file_put_contents($logfilename, date("[Y-m-d H:i:s] ") . "An error occurred during Bulk import.\n", FILE_APPEND);
	echo "An error occurred during Bulk import.\n";
	exit;
}
pg_query($conn, "COMMIT");

writeLog("Deleting extracted file..\n");
unlink("tmp/" . $srcFileName);

writeLog("Creating main table..\n");
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
	averagesignal smallint)");
if (!$result) {
	writeLog("An error occurred during Table creation.\n");
	exit;
}

writeLog("Populating main table..\n");	
$sql = "INSERT INTO $tempTableName SELECT DISTINCT ON (net, radio, area, mcc, cell) radio, mcc, net, area, cell, unit,
	ST_SetSRID(ST_MakePoint(lon, lat), 4326) As pos,
	range, samples, changeable, created, updated, averagesignal
	FROM $tempImportName";
$result = pg_query($conn, $sql);	
if (!$result) {
	writeLog("Error populating main table.\n");
	exit;
}

writeLog("Creating primary key..\n");
$tempTablePKey = $tempTableName . "_pkey";

$result = pg_query($conn, "ALTER TABLE $tempTableName
	ADD CONSTRAINT $tempTablePKey PRIMARY KEY (net, radio, area, mcc, cell)");
if (!$result) {
	writeLog("An error occurred during primary key creation.\n");
	exit;
}

writeLog("Creating indexes..\n");
$sql = "CREATE INDEX $tempGixName ON $tempTableName USING GIST (pos)";
$result = pg_query($conn, $sql);
if (!$result) {
	writeLog("Couldn't create $tempGixName.\n");
	exit;
}

writeLog("Clustering spatial index..\n");
$sql = "CLUSTER $tempTableName USING $tempGixName;";
$result = pg_query($conn, $sql);
if (!$result) {
	writeLog("Couldn't cluster $tempGixName.\n");
	exit;
}

// All writes done. Set table to Logged.
pg_query($conn, "ALTER TABLE $tempTableName SET LOGGED");

writeLog("Analyzing $tempTableName Table..\n");
$sql = "ANALYZE $tempTableName;";
$result = pg_query($conn, $sql);
if (!$result) {
	writeLog("Couldn't analyze Table.\n");
	exit;
}

writeLog("Creating LAC Table..\n");
$sql = "CREATE TABLE $tempLacTableName(
	radio text,
	mcc smallint, 
	net smallint,
	area integer,
	cPos geometry(POINT, 4326),
	outline geometry(GEOMETRY, 4326),
	size integer,
	id SERIAL)";
$result = pg_query($conn, $sql);
if (!$result) {
	writeLog("Couldn't create LAC Table.\n");
	exit;
}

writeLog("Populating LAC Table..\n");
$sql = "INSERT INTO $tempLacTableName SELECT radio, mcc, net, area, 
	null AS cPos,
	null AS outline,
	COUNT(*) AS size
	FROM $tempTableName
	GROUP BY area, radio, net, mcc";
$result = pg_query($conn, $sql);	
if (!$result) {
	writeLog("Error populating LAC Database.\n");
	exit;
}

writeLog("Creating primary key..\n");
$tempLacTablePKey = $tempLacTableName . "_pkey";

$result = pg_query($conn, "ALTER TABLE $tempLacTableName
	ADD CONSTRAINT $tempLacTablePKey PRIMARY KEY (radio, net, area, mcc)");
if (!$result) {
	writeLog("An error occurred during primary key creation.\n");
	exit;
}

writeLog("Computing LAC geometry..\n");

$i = 0;
$stepsize = 50000;

do{
	$j = $i + $stepsize;
	$sql = "UPDATE $tempLacTableName t1 SET cPos = ST_CENTROID(ST_COLLECT(ARRAY(SELECT t2.pos FROM $tempTableName t2 WHERE t2.area = t1.area AND t2.mcc = t1.mcc AND t2.net = t1.net AND t2.radio = t1.radio))),
			outline = ST_NULLABLECONCAVEHULL(ST_COLLECT(ARRAY(SELECT t2.pos FROM $tempTableName t2 WHERE t2.area = t1.area AND t2.mcc = t1.mcc AND t2.net = t1.net AND t2.radio = t1.radio)), 0.99) 
			WHERE id BETWEEN $i AND $j";
	$result = pg_query($conn, $sql);	
	if (!$result) {
		writeLog("Error Computing LAC geometry.\n");
		exit;
	}
	$i = $j;
	writeLog("$j complete!\n");
} while(pg_affected_rows($result) > 0);

writeLog("Droping id column..\n");
$sql = "ALTER TABLE $tempLacTableName DROP COLUMN id";
$result = pg_query($conn, $sql);
if (!$result) {
	writeLog("Couldn't drop column.\n");
	exit;
}

writeLog("Creating LAC index..\n");
$sql = "CREATE INDEX $tempLacGixName ON $tempLacTableName USING GIST (cPos)";
$result = pg_query($conn, $sql);
if (!$result) {
	writeLog("Couldn't create $tempLacGixName.\n");
	exit;
}

writeLog("Clustering spatial index..\n");
$sql = "CLUSTER $tempLacTableName USING $tempLacGixName";
$result = pg_query($conn, $sql);
if (!$result) {
	writeLog("Couldn't cluster $tempLacGixName.\n");
	exit;
}

writeLog("Analyzing $tempLacTableName..\n");
$sql = "ANALYZE $tempLacTableName";
$result = pg_query($conn, $sql);	
if (!$result) {
	writeLog("Couldn't analyze Table.\n");
	exit;
}

writeLog("Creating info table..\n");
$result = pg_query($conn, "CREATE TABLE IF NOT EXISTS $generalTableName(
	para text NOT NULL, 
	time timestamp, 
	sInfo text,
	iInfo integer,
	eInfo integer,
	PRIMARY KEY (para))");

if (!$result) {
	writeLog("An error occurred during general Table creation.\n");
	exit;
}

writeLog("Populating info table..\n");
$sql = "INSERT INTO $generalTableName VALUES ('$infoParam', CURRENT_TIMESTAMP, '$srcFileName', null, null)
	 ON CONFLICT (para) DO UPDATE SET time = CURRENT_TIMESTAMP, sInfo = '$srcFileName', iInfo = null, eInfo = null";
$result = pg_query($conn, $sql);	
if (!$result) {
	writeLog("Couldn't create Builddate Entry.\n");
	exit;
}

writeLog("Renaming Tables..\n");
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
writeLog("Done. Took $totaltime seconds.\n");
?>
