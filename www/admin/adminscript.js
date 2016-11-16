/* Copyright (C) 2016  Lehrstuhl für Technische Elektronik, Friedrich-Alexander-Universität Erlangen-Nürnberg */
/* https://github.com/lte-fau/MLS-Map/blob/master/LICENSE */

var isActive = true;

window.onfocus = function () { 
  isActive = true; 
}; 

window.onblur = function () { 
  isActive = false; 
}; 

function startDbBuilder(mode)
{
	$.post('startDbBuilder.php', {mode: mode, url: $("#linkBox").val()}, function(data){
		$("#tabs").tabs("option", "active", 2);
	});
}

function refreshConsole()
{
	if(isActive)
	{
		$.post('getConsole.php', {rows: 50}, function(data){
			$('#consoleDiv').empty();
			if(data == "NO_DATA")
			{			
				$("<p>Logfile is empty.</p>").appendTo("#consoleDiv");
			} else
			{
				var cData = data.split("|");
				for (var i = 0; i < cData.length; i++)
				{
					$("<p>" + cData[i] + "</p>").appendTo("#consoleDiv");
				}
				
				$("#consoleDiv").scrollTop($("#consoleDiv")[0].scrollHeight);
			}
		});
	}
	window.setTimeout(refreshConsole, 5000);
}

$(document).ready(function()
{
	$(function(){
		$("#tabs").tabs();
	});
	
	$("#rebuildOcidButton").button();
	$("#rebuildMlsButton").button();
	
	$("#linkBox").addClass("ui-widget ui-widget-content ui-corner-all");

	$.post('../getInfo.php', {para: 'MLS_DB_DATE'}, function(data){
		$("#mlsDbVersion").append(data);
	});
	
	$.post('../getInfo.php', {para: 'OCID_DB_DATE'}, function(data){
		$("#ocidDbVersion").append(data);
	});
	
	
	$("#rebuildOcidButton").click(function(){
		startDbBuilder("ocid");
	});
	
	$("#rebuildMlsButton").click(function(){
		startDbBuilder("mls");
	});
	
	refreshConsole();
});
