<?php
/* Copyright (C) 2016  Lehrstuhl für Technische Elektronik, Friedrich-Alexander-Universität Erlangen-Nürnberg */
/* https://github.com/lte-fau/MLS-Map/blob/master/LICENSE */
	include "secure.php";
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
	<title>MapView Adm</title>
	<meta charset="UTF-8">
	
	<link rel="stylesheet" href="../jquery-ui/jquery-ui.min.css" />
	<link rel="stylesheet" href="adminDesign.css" />
	
	<script src="../js/jquery.min.js"></script>
	<script src="../jquery-ui/jquery-ui.min.js"></script>
	<script src="adminscript.js"></script>
	
</head>
<body>

	<div id="adminDiv">
		<div id="tabs">
			<ul>
				<li><a href="#dbTab">Databases</a></li>
				<li><a href="#ssTab">Serverside Settings</a></li>
				<li><a href="#cTab">Console</a></li>
			</ul>
			<div id="dbTab">
				<div id="ocidDiv">
					<h1> OpenCellID: </h1>
					<p id="ocidDbVersion" class="infoText">Database Date: </p>
					<p>
					<p> Measurement DB seperate?
					<p>
					<button type="Button" id="rebuildOcidButton" class="buildBtn">Rebuild OCID</button>
				</div>
				
				<div id="mlsDiv">
					<h1> Mozilla Location Service: </h1>
					<p id="mlsDbVersion" class="infoText">Database Date: </p>
					<p>
					<label for="linkBox">Download URL:</label><br>
					<input type="text" name="mlsUrl" id="linkBox" class="TextBox"><br>
					<p>
					<button type="Button" id="rebuildMlsButton" class="buildBtn">Rebuild MLS</button>
				</div>
			</div>
			<div id="ssTab">
				<p></p>
			</div>
			<div id="cTab">
				<div id="consoleDiv">
				</div>
			</div>
		</div>
		<div id="logout"><a href="logout.php">Logout</a></div>
	</div>
	
</body>
</html>
