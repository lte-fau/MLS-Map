/* Copyright (C) 2016  Lehrstuhl für Technische Elektronik, Friedrich-Alexander-Universität Erlangen-Nürnberg */
/* https://github.com/lte-fau/MLS-Map/blob/master/LICENSE */

<?php
session_start();

if(isset($_SESSION['userid']))
{
	header('Location: admin.php');
	exit;
}
?>
