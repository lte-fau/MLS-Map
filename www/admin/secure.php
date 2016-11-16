<?php
/* Copyright (C) 2016  Lehrstuhl für Technische Elektronik, Friedrich-Alexander-Universität Erlangen-Nürnberg */
/* https://github.com/lte-fau/MLS-Map/blob/master/LICENSE */

session_start();

if(!isset($_SESSION['userid']))
{
	header('Location: index.php');
	echo '<META HTTP-EQUIV="Refresh" Content="0; URL=index.php">'; // Failsafe
	exit;
}
 
$userId = $_SESSION['userid'];

session_write_close(); // Disables session write to unlock session file
?>
