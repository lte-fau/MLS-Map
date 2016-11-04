<?php
/* Copyright (C) 2016  Lehrstuhl für Technische Elektronik, Friedrich-Alexander-Universität Erlangen-Nürnberg */
/* https://github.com/lte-fau/MLS-Map/blob/master/LICENSE */
session_start();

$para = $_POST["para"];

// Create connection
include "../db/db-settings.php";
$conn = pg_connect($connString)
	or die('Could not connect: ' . pg_last_error());
	
// Build a Versionstring
if($para == "DB_DATE_STRING") {
	// Get MLS Date
	$sql = "SELECT time FROM gInfo WHERE para = 'MLS_BUILD_DATE'";
	$result = pg_query($conn, $sql);

	if (!$result) {
	  echo "An error occurred while reading Data.";
	  exit;
	}
	
	$res = "<p> MLS-Database build: ";
	
	if(pg_num_rows($result) == 1)
	{
		$res .= pg_fetch_result($result, 0, 0);
	} else
		$res .= "No Data.";
	
	// Get OCID Date
	$sql = "SELECT time FROM gInfo WHERE para = 'OCID_BUILD_DATE'";
	$result = pg_query($conn, $sql);

	if (!$result) {
	  echo "An error occurred while reading Data.";
	  exit;
	}
	
	$res .= "<p> OCID-Database build: ";
	
	if(pg_num_rows($result) == 1)
	{
		$res .= pg_fetch_result($result, 0, 0);
	} else
		$res .= "No Data.";
	
} else if ($para == "MLS_DB_DATE") {
	$sql = "SELECT time FROM gInfo WHERE para = 'MLS_BUILD_DATE'";
	$result = pg_query($conn, $sql);

	if (!$result) {
	  echo "An error occurred while reading Data.";
	  exit;
	}

	if(pg_num_rows($result) == 1)
	{
		$res = pg_fetch_result($result, 0, 0);
	} else
		$res = "No Data.";
	
}else if ($para == "OCID_DB_DATE") {
	$sql = "SELECT time FROM gInfo WHERE para = 'OCID_BUILD_DATE'";
	$result = pg_query($conn, $sql);

	if (!$result) {
	  echo "An error occurred while reading Data.";
	  exit;
	}

	if(pg_num_rows($result) == 1)
	{
		$res = pg_fetch_result($result, 0, 0);
	} else
		$res = "No Data.";
}

pg_close($conn);
echo $res;
?>
