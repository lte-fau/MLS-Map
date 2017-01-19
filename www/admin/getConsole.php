<?php
/* Copyright (C) 2017  Lehrstuhl für Technische Elektronik, Friedrich-Alexander-Universität Erlangen-Nürnberg */
/* https://github.com/lte-fau/MLS-Map/blob/master/LICENSE */

$numLines = $_POST["lines"];
$res = "";

$file = file("tmp/log.txt");
$fileLength = count($file);

if($fileLength > 0)
{
	$firstLine = max(0, $fileLength - $numLines);

	for ($i = $firstLine; $i < $fileLength; $i++)
		$res .= $file[$i] . "|";
} else
	$res = "NO_DATA";

echo $res;	
?>
