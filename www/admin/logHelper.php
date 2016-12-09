<?php
/* Copyright (C) 2016  Lehrstuhl für Technische Elektronik, Friedrich-Alexander-Universität Erlangen-Nürnberg */
/* https://github.com/lte-fau/MLS-Map/blob/master/LICENSE */

function writeLog($str)
{
	$str = date("[Y-m-d H:i:s] ") . $str . "\n";
	file_put_contents("tmp/log.txt", $str, FILE_APPEND);
	echo $str;
}

function truncateLog($size)
{
	$fileContent = file("tmp/log.txt");
	$fileLength = count($fileContent);
	
	$nFile = fopen("tmp/log.txt", "w");
	
	for ($i = ($fileLength - $size); $i < $fileLength; $i++)
		fwrite($nFile, $fileContent[$i]);

	fclose($nFile);
}
?>
