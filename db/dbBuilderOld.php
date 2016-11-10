<?php
/* Copyright (C) 2016  Lehrstuhl für Technische Elektronik, Friedrich-Alexander-Universität Erlangen-Nürnberg */
/* https://github.com/lte-fau/MLS-Map/blob/master/LICENSE */

function getLines($file)
{
	$f = fopen($file, 'r');
	$lines = 0;

	while (!feof($f))
		$lines += substr_count(fread($f, 8192), "\n");

	fclose($f);
	return $lines;
}

$mtime = microtime(); 
$mtime = explode(" ",$mtime); 
$mtime = $mtime[1] + $mtime[0]; 
$starttime = $mtime; 

//__________Params___________
$mlsTableName = "mls";
$mlsLacTableName = "mlsLACs";
$generalTableName = "gInfo";

$mlsGixName = "mls_gix";
$mlsLacGixName = "mls_lac_gix";

$srcFileName = "full428.csv";
//___________________________

// Create connection
include "db-settings.php";
$conn = pg_connect($connString)
	or die('Could not connect: ' . pg_last_error());

echo "Connected successfully\n";

pg_query($conn, "DROP TABLE IF EXISTS $mlsTableName;");
pg_query($conn, "DROP TABLE IF EXISTS $mlsLacTableName;");

$result = pg_query($conn, "CREATE TABLE $mlsTableName(
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
	averagesignal smallint,
	PRIMARY KEY (net, radio, area, cell, mcc))");

if (!$result) {
  echo "An error occurred during Table creation.\n";
  exit;
}

$lines = getLines($srcFileName);
$myfile = fopen($srcFileName, "r") or die("Unable to open file!");
fgets($myfile);

$cLine = 1;
$numError = 0;

pg_query($conn, "BEGIN");

while(!feof($myfile)) {
	$csvArray = fgetcsv($myfile);
	$cLine++;
	if($cLine % 4096 == 0)
	{
		pg_query($conn,"COMMIT");
		echo "$cLine of $lines processed\n";
		pg_query($conn, "BEGIN");
	}
	if(count($csvArray) == 14)
	{
		if($csvArray[5] == "")
			$csvArray[5] = 0;
		if($csvArray[13] == "")
			$csvArray[13] = 0;
		
		$sql = "INSERT INTO $mlsTableName(radio, mcc, net, area, cell, unit, pos, range, samples, changeable, created, updated, averagesignal)
		VALUES (
		'$csvArray[0]',
		$csvArray[1], 
		$csvArray[2],
		$csvArray[3],
		$csvArray[4],
		$csvArray[5],
		ST_GeometryFromText('SRID=4326;POINT($csvArray[6] $csvArray[7])'),
		$csvArray[8],
		$csvArray[9],
		$csvArray[10],
		$csvArray[11],
		$csvArray[12],
		$csvArray[13]);";
		$result = pg_query($conn, $sql);

		if (!$result) {
			$numError++;
			echo "An error occurred while Inserting Data.\n" . $sql;
			echo "Skipping Line: " . $cLine . "\n";
		}
	} else
	{
		pg_query($conn, "COMMIT");
		echo "A total of " . $numError . " lines were skipped.\n";
		echo "Done creating main Table.\n";
	}
}
fclose($myfile);

echo "Creating indexes..\n";
$sql = "CREATE INDEX $mlsGixName ON $mlsTableName USING GIST (pos);";
$result = pg_query($conn, $sql);
if (!$result) {
	echo "Couldn't create $mlsGixName.\n";
	exit;
}


echo "Clustering spatial index..\n"	;
$sql = "CLUSTER $mlsTableName USING $mlsGixName;";
$result = pg_query($conn, $sql);
if (!$result) {
	echo "Couldn't cluster $mlsGixName.\n";
	exit;
}


echo "Analyzing $mlsTableName Table..\n";
$sql = "ANALYZE $mlsTableName;";
$result = pg_query($conn, $sql);
if (!$result) {
	echo "Couldn't analyze Table.\n";
	exit;
}


echo "Creating LAC Table..\n";
$sql = "CREATE TABLE $mlsLacTableName(
	radio char(10),
	mcc smallint, 
	net smallint,
	area integer,
	cPos geometry(POINT, 4326),
	outline geometry(GEOMETRY, 4326),
	size integer,
	PRIMARY KEY (net, radio, area, mcc))";
$result = pg_query($conn, $sql);
if (!$result) {
	echo "Couldn't create LAC Table.\n";
	exit;
}


echo "Populating LAC Table..\n";
$sql = "INSERT INTO $mlsLacTableName SELECT radio, mcc, net, area, 
	ST_CENTROID(ST_COLLECT(ARRAY(SELECT t2.pos FROM $mlsTableName t2 WHERE t2.area = t1.area AND t2.mcc = t1.mcc AND t2.net = t1.net AND t2.radio = t1.radio))) 
	AS cPos,
	ST_NULLABLECONCAVEHULL(ST_COLLECT(ARRAY(SELECT t2.pos FROM $mlsTableName t2 WHERE t2.area = t1.area AND t2.mcc = t1.mcc AND t2.net = t1.net AND t2.radio = t1.radio)), 0.98) 
	AS outline,
	COUNT(*) AS size
	FROM $mlsTableName t1
	GROUP BY t1.area, t1.radio, t1.net, t1.mcc";
$result = pg_query($conn, $sql);	
if (!$result) {
	echo "Error populating LAC Database.\n";
	exit;
}


echo "Creating LAC index..\n";
$sql = "CREATE INDEX $mlsLacGixName ON $mlsLacTableName USING GIST (cPos);";
$result = pg_query($conn, $sql);
if (!$result) {
	echo "Couldn't create $mlsLacGixName.\n";
	exit;
}


echo "Clustering spatial index..\n";
$sql = "CLUSTER $mlsLacTableName USING $mlsLacGixName";
$result = pg_query($conn, $sql);
if (!$result) {
	echo "Couldn't cluster $mlsLacGixName.\n";
	exit;
}


echo "Analyzing $mlsLacTableName..\n";
$sql = "ANALYZE $mlsLacTableName";
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
$sql = "INSERT INTO gInfo VALUES ('MLS_BUILD_DATE', CURRENT_TIMESTAMP, $srcFileName, $lines, $numError)
	 ON CONFLICT (para) DO UPDATE SET time = CURRENT_TIMESTAMP, sInfo = $srcFileName, iInfo = $lines, eInfo = $numError";
$result = pg_query($conn, $sql);	
if (!$result) {
	echo "Couldn't create Builddate Entry.\n";
	exit;
}

pg_close($conn);

$mtime = microtime(); 
$mtime = explode(" ",$mtime); 
$mtime = $mtime[1] + $mtime[0]; 
$endtime = $mtime; 
$totaltime = ($endtime - $starttime); 
echo "Done. Took " . $totaltime . " seconds.\n"; 
?>
