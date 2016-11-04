/* Copyright (C) 2016  Lehrstuhl für Technische Elektronik, Friedrich-Alexander-Universität Erlangen-Nürnberg */
/* https://github.com/lte-fau/MLS-Map/blob/master/LICENSE */

$(document).ready(function()
{
	$( function() {
		$( "#tabs" ).tabs();
	} );
	
	$.post('../getInfo.php', {para: 'MLS_DB_DATE'}, function(data){
		$("#mlsDbVersion").append(data);
	});
	
	$.post('../getInfo.php', {para: 'OCID_DB_DATE'}, function(data){
		$("#ocidDbVersion").append(data);
	});
});
