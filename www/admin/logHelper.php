<?php
/* Copyright (C) 2016  Lehrstuhl für Technische Elektronik, Friedrich-Alexander-Universität Erlangen-Nürnberg */
/* https://github.com/lte-fau/MLS-Map/blob/master/LICENSE */

function writeLog($str)
{
	file_put_contents("tmp/log.txt", date("[Y-m-d H:i:s] ") . $str, FILE_APPEND);
    echo date("[Y-m-d H:i:s] ") . $str;
}
?>
