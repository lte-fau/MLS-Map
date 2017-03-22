/* Copyright (C) 2017  Lehrstuhl für Technische Elektronik, Friedrich-Alexander-Universität Erlangen-Nürnberg */
/* https://github.com/lte-fau/MLS-Map/blob/master/LICENSE */

var isActive = true;
var consoleIsHeld = false;

window.onfocus = function () { 
  isActive = true; 
}; 

window.onblur = function () { 
  isActive = false; 
}; 

function startDbBuilder(mode)
{
	$("#tabs").tabs("option", "active", 2);
	
	$.post('startDbBuilder.php', {mode: mode}, function(data){
	});
}

function refreshConsole()
{
	if(isActive)
	{
		$.post('getConsole.php', {lines: 500}, function(data){
			$('#consoleDiv').empty();
			if(data == "NO_DATA")
				$("<p>Logfile is empty.</p>").appendTo("#consoleDiv");
			else
			{
				var cData = data.split("|");
				
				for (var i = 0; i < cData.length; i++)
					$("<p>" + cData[i] + "</p>").appendTo("#consoleDiv");
				
				if(!consoleIsHeld)
					$("#consoleDiv").scrollTop($("#consoleDiv")[0].scrollHeight);
			}
		});
	}
	window.setTimeout(refreshConsole, 5000);
}

function saveSettings()
{	
	$.post('saveSettings.php', {ViewExtendFactor: $("#ViewExtendFactor").val(),
								MncDisableLevel: $("#MncDisableLevel").val(),
								ForceLacSortLevel: $("#ForceLacSortLevel").val(),
								ForceClusteredLacSortLevel: $("#ForceClusteredLacSortLevel").val(),
								ForceClusteredCellsLevel: $("#ForceClusteredCellsLevel").val(),
								CellClusterGridSize: $("#CellClusterGridSize").val(),
								LacClusterGridSize: $("#LacClusterGridSize").val(),
								HeatGridSize: $("#HeatGridSize").val(),
								HeatMaxCellLevel: $("#HeatMaxCellLevel").val(),
								HeatUseLacLevel: $("#HeatUseLacLevel").val(),
								MaxAvgDistanceRatio: $("#MaxAvgDistanceRatio").val()}, function(data){
		if(data == "SAVED")
			alert("Settings Saved.");
	});
}

$(document).ready(function()
{
	$("#consoleDiv").on({
		mousedown: function () { consoleIsHeld = true; },
		mouseup: function () { consoleIsHeld = false; }
	});


	$(function(){
		$("#tabs").tabs();
	});
	
	$("#rebuildOcidButton").button();
	$("#rebuildMlsButton").button();
	$("#saveSettingsButton").button();
	$("#resetSettingsButton").button();
	
	$("#linkBox").addClass("ui-widget ui-widget-content ui-corner-all");
	$(".settingsTextBox").addClass("ui-widget ui-widget-content ui-corner-all");

	$.post('../getInfo.php', {para: 'MLS_DB_DATE'}, function(data){
		$("#mlsDbVersion").append(data);
	});
	
	$.post('../getInfo.php', {para: 'OCID_DB_DATE'}, function(data){
		$("#ocidDbVersion").append(data);
	});
	
	$("#saveSettingsButton").click(function(){
		saveSettings();
	});
	
	$("#resetSettingsButton").click(function(){
		$.post('saveSettings.php', {}, function(data){
			if(data == "SAVED")
				location.reload();
		});
	});
	
	$("#rebuildOcidButton").click(function(){
		startDbBuilder("ocid");
	});
	
	$("#rebuildMlsButton").click(function(){
		startDbBuilder("mls");
	});
	
	refreshConsole();
});
