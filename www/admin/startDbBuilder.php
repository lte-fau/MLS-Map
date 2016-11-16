<?php
/* Copyright (C) 2016  Lehrstuhl für Technische Elektronik, Friedrich-Alexander-Universität Erlangen-Nürnberg */
/* https://github.com/lte-fau/MLS-Map/blob/master/LICENSE */

include "secure.php";

$pMode = $_POST["mode"];
$url = $_POST["url"];

if($pMode == "ocid")
	$mode = "ocid";
else if($pMode == "mls")
	$mode = "mls";

//exec('echo /usr/bin/php -q dbBuilder.php $mode $url | at now');

echo "DbBuilder starting..";
exec("/usr/bin/php -q dbBuilder.php $mode $url &");
?>
