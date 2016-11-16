<?php
/* Copyright (C) 2016  Lehrstuhl für Technische Elektronik, Friedrich-Alexander-Universität Erlangen-Nürnberg */
/* https://github.com/lte-fau/MLS-Map/blob/master/LICENSE */
//include "secure.php";
include "db-settings.php";

if($argv[1] == "ocid")
{
	//__________Params___________
	$fileName = "OCIDTowers.csv.gz";

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

$logfilename = "log.txt";


file_put_contents($logfilename, "<br>*******************************************\n", FILE_APPEND);
file_put_contents($logfilename, "******* Starting dbBuilder for $argv[1] *******\n", FILE_APPEND);
file_put_contents($logfilename, "*******************************************\n<br>", FILE_APPEND);


file_put_contents($logfilename, date("[Y-m-d H:i:s] ") . "Downloading datafile..\n", FILE_APPEND);
echo "Downloading datafile..\n";
file_put_contents($fileName, fopen($dataURL, 'r'));

file_put_contents($logfilename, date("[Y-m-d H:i:s] ") . "Extracting file..\n", FILE_APPEND);
echo "Extracting file..\n";
$buffer_size = 1048576; // 1MiB
$outputFileName = str_replace('.gz', '', $fileName); 
$srcFileName = $outputFileName;

$file = gzopen($fileName, 'rb');
$outputFile = fopen($outputFileName, 'wb'); 

if($file == false || $outputFile == false)
{
	exit;
}

while (!gzeof($file)) {
    fwrite($outputFile, gzread($file, $buffer_size));
}

fclose($outputFile);
gzclose($file);


file_put_contents($logfilename, date("[Y-m-d H:i:s] ") . "Deleting downloaded file..\n", FILE_APPEND);
echo "Deleting downloaded file..\n";
unlink($fileName);

if($argv[1] == "mls")
{
	file_put_contents($logfilename, date("[Y-m-d H:i:s] ") . "Removing carriage return characters..\n", FILE_APPEND);
	echo "Removing carriage return characters..\n";
	
	
	$file_read = fopen($srcFileName, "r");
	$newFileName = "Mod" . $srcFileName;
	$file_write = fopen($newFileName, "w+");
	
	while(!feof($file_read))
	{
		$file_line = fgets($file_read);
		$file_trim = str_replace("\r", '', $file_line); 
		fwrite($file_write, $file_trim);
	}
	
	fclose($file_read);
	fclose($file_write);
	
	unlink(srcFileName);
	$srcFileName = $newFileName;
}


file_put_contents($logfilename, date("[Y-m-d H:i:s] ") . "All done! Starting DbBuilder..\n", FILE_APPEND);
echo "All done! Starting DbBuilder..\n";


// Create connection
include "db-settings.php";
$conn = pg_connect($connString . " sslmode=disable")
	or die('Could not connect: ' . pg_last_error());

file_put_contents($logfilename, date("[Y-m-d H:i:s] ") . "Connected to database successfully.\n", FILE_APPEND);
echo "Connected to database successfully.\n";

file_put_contents($logfilename, date("[Y-m-d H:i:s] ") . "Creating bulk-import table..\n", FILE_APPEND);
echo "Creating bulk-import table..\n";
pg_query($conn, "DROP TABLE IF EXISTS $tempTableName");
pg_query($conn, "DROP TABLE IF EXISTS $tempLacTableName");

pg_query($conn, "DROP TABLE IF EXISTS $tempImportName");

// COPY is fastest wenn done in the same transaction as CREATE TABLE
pg_query($conn, "BEGIN");

$result = pg_query($conn, "CREATE UNLOGGED TABLE $tempImportName(
	radio char(5),
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
	file_put_contents($logfilename, date("[Y-m-d H:i:s] ") . "An error occurred during Table creation.\n", FILE_APPEND);
	echo "An error occurred during Table creation.\n";
	exit;
}


echo "Importing Data..\n";
$result = pg_query($conn, "SELECT import_csv_file_to_table('$tempImportName', '$srcFileName')");
if (!$result) {
	file_put_contents($logfilename, date("[Y-m-d H:i:s] ") . "An error occurred during Bulk import.\n", FILE_APPEND);
	echo "An error occurred during Bulk import.\n";
	exit;
}
pg_query($conn, "COMMIT");

file_put_contents($logfilename, date("[Y-m-d H:i:s] ") . "Creating main table..\n", FILE_APPEND);
echo "Creating main table..\n";
$result = pg_query($conn, "CREATE UNLOGGED TABLE $tempTableName(
	radio char(10),
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
	file_put_contents($logfilename, date("[Y-m-d H:i:s] ") . "An error occurred during Table creation.\n", FILE_APPEND);
	echo "An error occurred during Table creation.\n";
	exit;
}


file_put_contents($logfilename, date("[Y-m-d H:i:s] ") . "Populating main table..\n", FILE_APPEND);
echo "Populating main table..\n";		
$sql = "INSERT INTO $tempTableName SELECT DISTINCT ON (net, radio, area, mcc, cell) radio, mcc, net, area, cell, unit,
	ST_SetSRID(ST_MakePoint(lon, lat), 4326) As pos,
	range, samples, changeable, created, updated, averagesignal
	FROM $tempImportName";
$result = pg_query($conn, $sql);	
if (!$result) {
	file_put_contents($logfilename, date("[Y-m-d H:i:s] ") . "Error populating main table.\n", FILE_APPEND);
	echo "Error populating main table.\n";
	exit;
}


file_put_contents($logfilename, date("[Y-m-d H:i:s] ") . "Creating primary key..\n", FILE_APPEND);	
echo "Creating primary key..\n";
$tempTablePKey = $tempTableName . "_pkey";

$result = pg_query($conn, "ALTER TABLE $tempTableName
	ADD CONSTRAINT $tempTablePKey PRIMARY KEY (net, radio, area, mcc, cell)");
if (!$result) {
	file_put_contents($logfilename, date("[Y-m-d H:i:s] ") . "An error occurred during primary key creation.\n", FILE_APPEND);
	echo "An error occurred during primary key creation.\n";
	exit;
}


file_put_contents($logfilename, date("[Y-m-d H:i:s] ") . "Creating indexes..\n", FILE_APPEND);
echo "Creating indexes..\n";
$sql = "CREATE INDEX $tempGixName ON $tempTableName USING GIST (pos);";
$result = pg_query($conn, $sql);
if (!$result) {
	file_put_contents($logfilename, date("[Y-m-d H:i:s] ") . "Couldn't create $tempGixName.\n", FILE_APPEND);
	echo "Couldn't create $tempGixName.\n";
	exit;
}

file_put_contents($logfilename, date("[Y-m-d H:i:s] ") . "Clustering spatial index..\n", FILE_APPEND);
echo "Clustering spatial index..\n";
$sql = "CLUSTER $tempTableName USING $tempGixName;";
$result = pg_query($conn, $sql);
if (!$result) {
	file_put_contents($logfilename, date("[Y-m-d H:i:s] ") . "Couldn't cluster $tempGixName.\n", FILE_APPEND);
	echo "Couldn't cluster $tempGixName.\n";
	exit;
}

// All writes done. Set table to Logged.
pg_query($conn, "ALTER TABLE $tempTableName SET LOGGED");


file_put_contents($logfilename, date("[Y-m-d H:i:s] ") . "Analyzing $tempTableName Table..\n", FILE_APPEND);
echo "Analyzing $tempTableName Table..\n";
$sql = "ANALYZE $tempTableName;";
$result = pg_query($conn, $sql);
if (!$result) {
	file_put_contents($logfilename, date("[Y-m-d H:i:s] ") . "Couldn't analyze Table.\n", FILE_APPEND);
	echo "Couldn't analyze Table.\n";
	exit;
}

file_put_contents($logfilename, date("[Y-m-d H:i:s] ") . "Creating LAC Table..\n", FILE_APPEND);
echo "Creating LAC Table..\n";
$sql = "CREATE TABLE $tempLacTableName(
	radio char(10),
	mcc smallint, 
	net smallint,
	area integer,
	cPos geometry(POINT, 4326),
	outline geometry(GEOMETRY, 4326),
	size integer,
	id SERIAL)";
$result = pg_query($conn, $sql);
if (!$result) {
	file_put_contents($logfilename, date("[Y-m-d H:i:s] ") . "Couldn't create LAC Table.\n", FILE_APPEND);
	echo "Couldn't create LAC Table.\n";
	exit;
}


file_put_contents($logfilename, date("[Y-m-d H:i:s] ") . "Populating LAC Table..\n", FILE_APPEND);
echo "Populating LAC Table..\n";
$sql = "INSERT INTO $tempLacTableName SELECT radio, mcc, net, area, 
	null AS cPos,
	null AS outline,
	COUNT(*) AS size
	FROM $tempTableName
	GROUP BY area, radio, net, mcc";
$result = pg_query($conn, $sql);	
if (!$result) {
	file_put_contents($logfilename, date("[Y-m-d H:i:s] ") . "Error populating LAC Database.\n", FILE_APPEND);
	echo "Error populating LAC Database.\n";
	exit;
}


file_put_contents($logfilename, date("[Y-m-d H:i:s] ") . "Creating primary key..\n", FILE_APPEND);
echo "Creating primary key..\n";
$tempLacTablePKey = $tempLacTableName . "_pkey";

$result = pg_query($conn, "ALTER TABLE $tempLacTableName
	ADD CONSTRAINT $tempLacTablePKey PRIMARY KEY (radio, net, area, mcc)");
if (!$result) {
	file_put_contents($logfilename, date("[Y-m-d H:i:s] ") . "An error occurred during primary key creation.\n", FILE_APPEND);
	echo "An error occurred during primary key creation.\n";
	exit;
}


file_put_contents($logfilename, date("[Y-m-d H:i:s] ") . "Computing LAC geometry..\n", FILE_APPEND);
echo "Computing LAC geometry..\n";

$i = 0;
$stepsize = 50000;

do{
	$j = $i + $stepsize;
	$sql = "UPDATE $tempLacTableName t1 SET cPos = ST_CENTROID(ST_COLLECT(ARRAY(SELECT t2.pos FROM $tempTableName t2 WHERE t2.area = t1.area AND t2.mcc = t1.mcc AND t2.net = t1.net AND t2.radio = t1.radio))),
			outline = ST_NULLABLECONCAVEHULL(ST_COLLECT(ARRAY(SELECT t2.pos FROM $tempTableName t2 WHERE t2.area = t1.area AND t2.mcc = t1.mcc AND t2.net = t1.net AND t2.radio = t1.radio)), 0.99) 
			WHERE id BETWEEN $i AND $j";
	$result = pg_query($conn, $sql);	
	if (!$result) {
		file_put_contents($logfilename, date("[Y-m-d H:i:s] ") . "Error Computing LAC geometry.\n", FILE_APPEND);
		echo "Error Computing LAC geometry.\n";
		exit;
	}
	$i = $j;
	file_put_contents($logfilename, date("[Y-m-d H:i:s] ") . "$j complete!\n", FILE_APPEND);
	echo "$j complete!\n";
} while(pg_affected_rows($result) > 0);


file_put_contents($logfilename, date("[Y-m-d H:i:s] ") . "Droping id column..\n", FILE_APPEND);
echo "Droping id column..\n";
$sql = "ALTER TABLE $tempLacTableName DROP COLUMN id";
$result = pg_query($conn, $sql);
if (!$result) {
	file_put_contents($logfilename, date("[Y-m-d H:i:s] ") . "Couldn't drop column.\n", FILE_APPEND);
	echo "Couldn't drop column.\n";
	exit;
}


file_put_contents($logfilename, date("[Y-m-d H:i:s] ") . "Creating LAC index..\n", FILE_APPEND);
echo "Creating LAC index..\n";
$sql = "CREATE INDEX $tempLacGixName ON $tempLacTableName USING GIST (cPos)";
$result = pg_query($conn, $sql);
if (!$result) {
	file_put_contents($logfilename, date("[Y-m-d H:i:s] ") . "Couldn't create $tempLacGixName.\n", FILE_APPEND);
	echo "Couldn't create $tempLacGixName.\n";
	exit;
}


file_put_contents($logfilename, date("[Y-m-d H:i:s] ") . "Clustering spatial index..\n", FILE_APPEND);
echo "Clustering spatial index..\n";
$sql = "CLUSTER $tempLacTableName USING $tempLacGixName";
$result = pg_query($conn, $sql);
if (!$result) {
	file_put_contents($logfilename, date("[Y-m-d H:i:s] ") . "Couldn't cluster $tempLacGixName.\n", FILE_APPEND);
	echo "Couldn't cluster $tempLacGixName.\n";
	exit;
}


file_put_contents($logfilename, date("[Y-m-d H:i:s] ") . "Analyzing $tempLacTableName..\n", FILE_APPEND);
echo "Analyzing $tempLacTableName..\n";
$sql = "ANALYZE $tempLacTableName";
$result = pg_query($conn, $sql);	
if (!$result) {
	file_put_contents($logfilename, date("[Y-m-d H:i:s] ") . "Couldn't analyze Table.\n", FILE_APPEND);
	echo "Couldn't analyze Table.\n";
	exit;
}


file_put_contents($logfilename, date("[Y-m-d H:i:s] ") . "Creating info table..\n", FILE_APPEND);
echo "Creating info table..\n";
// Main Db done. Create Versionstamp
$result = pg_query($conn, "CREATE TABLE IF NOT EXISTS $generalTableName(
	para char(50) NOT NULL, 
	time timestamp, 
	sInfo char(50),
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

file_put_contents($logfilename, date("[Y-m-d H:i:s] ") . "Renaming Tables..\n", FILE_APPEND);
echo "Renaming Tables..\n";
pg_query($conn, "DROP TABLE IF EXISTS $finalTableName");
pg_query($conn, "DROP TABLE IF EXISTS $finalLacTableName");

pg_query($conn, "ALTER TABLE $tempTableName RENAME TO $finalTableName");
pg_query($conn, "ALTER TABLE $tempLacTableName RENAME TO $finalLacTableName");

pg_query($conn, "ALTER INDEX {$tempTableName}_pkey RENAME TO {$finalGixName}_pkey");
pg_query($conn, "ALTER INDEX {$tempLacTableName}_pkey RENAME TO {$finalLacGixName}_pkey");

pg_query($conn, "ALTER INDEX $tempGixName RENAME TO $finalGixName");
pg_query($conn, "ALTER INDEX $tempLacGixName RENAME TO $finalLacGixName");


pg_close($conn);

$mtime = microtime(); 
$mtime = explode(" ",$mtime); 
$mtime = $mtime[1] + $mtime[0]; 
$endtime = $mtime; 
$totaltime = ($endtime - $starttime); 
file_put_contents($logfilename, date("[Y-m-d H:i:s] ") . "Done. Took " . $totaltime . " seconds.\n", FILE_APPEND);
echo "Done. Took " . $totaltime . " seconds.\n"; 
?>
