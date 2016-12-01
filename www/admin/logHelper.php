<?php
/* Copyright (C) 2016  Lehrstuhl für Technische Elektronik, Friedrich-Alexander-Universität Erlangen-Nürnberg */
/* https://github.com/lte-fau/MLS-Map/blob/master/LICENSE */

function writeLog($str)
{
	$str = date("[Y-m-d H:i:s] ") . $str . "\n";
	file_put_contents("tmp/log.txt", $str, FILE_APPEND);
    echo $str;
}
?>
