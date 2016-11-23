<?php
/* Copyright (C) 2016  Lehrstuhl für Technische Elektronik, Friedrich-Alexander-Universität Erlangen-Nürnberg */
/* https://github.com/lte-fau/MLS-Map/blob/master/LICENSE */

include "secure.php";
$res = "";

$file = fopen("tmp/log.txt","r");
if($file != false)
{
	while(!feof($file))
		$res .= fgets($file) . "|";

	fclose($file);
} else
	$res = "NO_DATA";

echo $res;	
?>
