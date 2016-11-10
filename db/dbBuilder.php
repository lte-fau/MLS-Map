<?php
/* Copyright (C) 2016  Lehrstuhl für Technische Elektronik, Friedrich-Alexander-Universität Erlangen-Nürnberg */
/* https://github.com/lte-fau/MLS-Map/blob/master/LICENSE */

include "db-settings.php";


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
//___________________________


$mtime = microtime(); 
$mtime = explode(" ",$mtime); 
$mtime = $mtime[1] + $mtime[0]; 
$starttime = $mtime; 

echo "Downloading datafile..\n";
//file_put_contents($fileName, fopen("http://opencellid.org/downloads/?apiKey=" . $ocidAPIKey . "&filename=cell_towers_diff-2016110619.csv.gz", 'r'));

echo "Extracting file..\n";
$buffer_size = 1048576; // 1MiB
$outputFileName = str_replace('.gz', '', $fileName); 
$srcFileName = $outputFileName;
/*
$file = gzopen($fileName, 'rb');
$outputFile = fopen($outputFileName, 'wb'); 

while (!gzeof($file)) {
    fwrite($outputFile, gzread($file, $buffer_size));
}

fclose($outputFile);
gzclose($file);
*/
echo "Deleting downloaded file..\n";
//unlink($fileName);

echo "All done! Starting DbBuilder..\n";


// Create connection
include "db-settings.php";
$conn = pg_connect($connString)
	or die('Could not connect: ' . pg_last_error());

echo "Connected to database successfully.\n";


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
	echo "An error occurred during Table creation.\n";
	exit;
}


echo "Importing Data..\n";
$result = pg_query($conn, "SELECT import_csv_file_to_table('$tempImportName', '$srcFileName')");
if (!$result) {
	echo "An error occurred during Bulk import.\n";
	exit;
}
pg_query($conn, "COMMIT");

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
	echo "An error occurred during Table creation.\n";
	exit;
}


echo "Populating main table..\n";		
$sql = "INSERT INTO $tempTableName SELECT DISTINCT ON (net, radio, area, mcc, cell) radio, mcc, net, area, cell, unit,
	ST_SetSRID(ST_MakePoint(lon, lat), 4326) As pos,
	range, samples, changeable, created, updated, averagesignal
	FROM $tempImportName";
$result = pg_query($conn, $sql);	
if (!$result) {
	echo "Error populating main table.\n";
	exit;
}

	
echo "Creating primary key..\n";
$tempTablePKey = $tempTableName . "_pkey";

$result = pg_query($conn, "ALTER TABLE $tempTableName
	ADD CONSTRAINT $tempTablePKey PRIMARY KEY (net, radio, area, mcc, cell)");
if (!$result) {
	echo "An error occurred during primary key creation.\n";
	exit;
}

echo "Creating indexes..\n";
$sql = "CREATE INDEX $tempGixName ON $tempTableName USING GIST (pos);";
$result = pg_query($conn, $sql);
if (!$result) {
	echo "Couldn't create $tempGixName.\n";
	exit;
}


echo "Clustering spatial index..\n"	;
$sql = "CLUSTER $tempTableName USING $tempGixName;";
$result = pg_query($conn, $sql);
if (!$result) {
	echo "Couldn't cluster $tempGixName.\n";
	exit;
}

// All writes done. Set table to Logged.
pg_query($conn, "ALTER TABLE $tempTableName SET LOGGED");


echo "Analyzing $tempTableName Table..\n";
$sql = "ANALYZE $tempTableName;";
$result = pg_query($conn, $sql);
if (!$result) {
	echo "Couldn't analyze Table.\n";
	exit;
}


echo "Creating LAC Table..\n";
$sql = "CREATE UNLOGGED TABLE $tempLacTableName(
	radio char(10),
	mcc smallint, 
	net smallint,
	area integer,
	cPos geometry(POINT, 4326),
	outline geometry(GEOMETRY, 4326),
	size integer)";
$result = pg_query($conn, $sql);
if (!$result) {
	echo "Couldn't create LAC Table.\n";
	exit;
}


echo "Populating LAC Table..\n";
$sql = "INSERT INTO $tempLacTableName SELECT radio, mcc, net, area, 
	null AS cPos,
	null AS outline,
	COUNT(*) AS size
	FROM $tempTableName
	GROUP BY area, radio, net, mcc";
$result = pg_query($conn, $sql);	
if (!$result) {
	echo "Error populating LAC Database.\n";
	exit;
}

echo "Creating primary key..\n";
$tempLacTablePKey = $tempLacTableName . "_pkey";

$result = pg_query($conn, "ALTER TABLE $tempLacTableName
	ADD CONSTRAINT $tempLacTablePKey PRIMARY KEY (radio, net, area, mcc)");
if (!$result) {
  echo "An error occurred during primary key creation.\n";
  exit;
}

echo "Computing LAC geometry..\n";
$sql = "UPDATE $tempLacTableName t1 SET cPos = ST_CENTROID(ST_COLLECT(ARRAY(SELECT pos FROM $tempTableName t2 WHERE t2.area = t1.area AND t2.mcc = t1.mcc AND t2.net = t1.net AND t2.radio = t1.radio))),
		outline = ST_NULLABLECONCAVEHULL(ST_COLLECT(ARRAY(SELECT t2.pos FROM $tempTableName t2 WHERE t2.area = t1.area AND t2.mcc = t1.mcc AND t2.net = t1.net AND t2.radio = t1.radio)), 0.98)";
$result = pg_query($conn, $sql);	
if (!$result) {
	echo "Error Computing LAC geometry.\n";
	exit;
}

echo "Creating LAC index..\n";
$sql = "CREATE INDEX $tempLacGixName ON $tempLacTableName USING GIST (cPos);";
$result = pg_query($conn, $sql);
if (!$result) {
	echo "Couldn't create $tempLacGixName.\n";
	exit;
}


echo "Clustering spatial index..\n";
$sql = "CLUSTER $tempLacTableName USING $tempLacGixName";
$result = pg_query($conn, $sql);
if (!$result) {
	echo "Couldn't cluster $tempLacGixName.\n";
	exit;
}


// All writes done. Set table to Logged.
pg_query($conn, "ALTER TABLE $tempLacTableName SET LOGGED");


echo "Analyzing $tempLacTableName..\n";
$sql = "ANALYZE $tempLacTableName";
$result = pg_query($conn, $sql);	
if (!$result) {
	echo "Couldn't analyze Table.\n";
	exit;
}

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
  echo "An error occurred during general Table creation.\n";
  exit;
}

echo "Populating info table..\n";
$sql = "INSERT INTO $generalTableName VALUES ('$infoParam', CURRENT_TIMESTAMP, '$srcFileName', $lines, $numError)
	 ON CONFLICT (para) DO UPDATE SET time = CURRENT_TIMESTAMP, sInfo = '$srcFileName', iInfo = $lines, eInfo = $numError";
$result = pg_query($conn, $sql);	
if (!$result) {
	echo "Couldn't create Builddate Entry.\n";
	exit;
}

echo "Renaming Tables..\n";
pg_query($conn, "DROP TABLE IF EXISTS $finalTableName");
pg_query($conn, "DROP TABLE IF EXISTS $finalLacTableName");

pg_query($conn, "ALTER TABLE $tempTableName RENAME TO $finalTableName");
pg_query($conn, "ALTER TABLE $tempLacTableName RENAME TO $finalLacTableName");


pg_query($conn, "ALTER INDEX $tempGixName RENAME TO $finalGixName");
pg_query($conn, "ALTER INDEX $tempLacGixName RENAME TO $finalLacGixName");


pg_close($conn);

$mtime = microtime(); 
$mtime = explode(" ",$mtime); 
$mtime = $mtime[1] + $mtime[0]; 
$endtime = $mtime; 
$totaltime = ($endtime - $starttime); 
echo "Done. Took " . $totaltime . " seconds.\n"; 
?>
