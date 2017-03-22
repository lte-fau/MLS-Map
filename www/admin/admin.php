<?php
/* Copyright (C) 2017  Lehrstuhl für Technische Elektronik, Friedrich-Alexander-Universität Erlangen-Nürnberg */
/* https://github.com/lte-fau/MLS-Map/blob/master/LICENSE */
	include "secure.php";
	include "../getSettings.php";
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
	<title>MapView Adm</title>
	<meta charset="UTF-8">
	
	<link rel=icon href="../favicon.png">
	
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
					<button type="Button" id="rebuildOcidButton" class="buildBtn">Rebuild OCID</button>
				</div>
				
				<div id="mlsDiv">
					<h1> Mozilla Location Service: </h1>
					<p id="mlsDbVersion" class="infoText">Database Date: </p>
					<p>
					<button type="Button" id="rebuildMlsButton" class="buildBtn">Rebuild MLS</button>
				</div>
			</div>
			<div id="ssTab">
				<div id="leftDiv">
					<label for="ForceClusteredCellsLevel">Zoomlevel at witch cells are clustered:</label><br>
					<input type="text" class="settingsTextBox" id="ForceClusteredCellsLevel" title="" value=<?php echo  $paraForceClusteredCellsLevel ?>><br><br>
					<label for="ForceLacSortLevel">Zoomlevel at witch to lacSort:</label><br>
					<input type="text" class="settingsTextBox" id="ForceLacSortLevel" title="" value=<?php echo  $paraForceLacSortLevel ?>><br><br>
					<label for="ForceClusteredLacSortLevel">Zoomlevel at witch lacSort is clustered:</label><br>
					<input type="text" class="settingsTextBox" id="ForceClusteredLacSortLevel" title="" value=<?php echo  $paraForceClusteredLacSortLevel ?>><br><br>
					<br>
					<label for="HeatMaxCellLevel">Heatmap Cluster level:</label><br>
					<input type="text" class="settingsTextBox" id="HeatMaxCellLevel" title="" value=<?php echo  $paraHeatMaxCellLevel ?>><br><br>
					<label for="HeatUseLacLevel">Heatmap LAC level:</label><br>
					<input type="text" class="settingsTextBox" id="HeatUseLacLevel" title="" value=<?php echo  $paraHeatUseLacLevel ?>><br><br>
					<br>
					<label for="MaxAvgDistanceRatio">Max Avg. Distange Ratio (Invalid cell detection):</label><br>
					<input type="text" class="settingsTextBox" id="MaxAvgDistanceRatio" title="" value=<?php echo  $paraMaxAvgDistanceRatio ?>><br><br>
				</div>
				
				<div id="rightDiv">
					<label for="CellClusterGridSize">Clustered cells gridsize:</label><br>
					<input type="text" class="settingsTextBox" id="CellClusterGridSize" title="" value=<?php echo  $paraCellClusterGridSize ?>><br><br>
					<label for="LacClusterGridSize">Clustered LACs gridsize:</label><br>
					<input type="text" class="settingsTextBox" id="LacClusterGridSize" title="" value=<?php echo  $paraLacClusterGridSize ?>><br><br>
					
					<label for="HeatGridSize">Heatmap resolution:</label><br>
					<input type="text" class="settingsTextBox" id="HeatGridSize" title="" value=<?php echo  $paraHeatGridSize ?>><br><br>
					<br>
					<label for="ViewExtendFactor">Screen extension Factor:</label><br>
					<input type="text" class="settingsTextBox" id="ViewExtendFactor" title="" value=<?php echo  $paraViewExtendFactor ?>><br><br>
					<label for="MncDisableLevel">MNC disable level:</label><br>
					<input type="text" class="settingsTextBox" id="MncDisableLevel" title="" value=<?php echo  $paraMncDisableLevel ?>><br><br>
					<p>
					<button type="Button" id="resetSettingsButton" class="buildBtn">Reset</button>
					<button type="Button" id="saveSettingsButton" class="buildBtn">Save</button>
				</div>
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
