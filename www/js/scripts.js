/* Copyright (C) 2016  Lehrstuhl für Technische Elektronik, Friedrich-Alexander-Universität Erlangen-Nürnberg */
/* https://github.com/lte-fau/MLS-Map/blob/master/LICENSE */
//____ Settings ____
var paraFilterLACs = true; 					// Remove small LACs
var paraLacFilterLimit = 10;				// Minimum Location Area size to not be filtered

var paraIgnoreOldData = false;				// Ignore Cell Data that hasn't been modified in some time
var paraIgnoreDataAge = 7776000;			// Max Age (90d in seconds)

var paraCellClusterDisableLevel = 17;		// Zoom level at which cell clustering is Disabled
var paraCellMaxCellAmount = 600;			// Max Cell amount at which clustering is Disabled
var paraCellClusterRadius = 40;				// Cell cluster radius

var paraClusteredClusterRadius = 110; 		// Radius of Clusters in Standard Mode

var paraLACClusteredClusterRadius = 90;		// Radius of Clusters in clustered LAC Mode
var paraLACClusterRadius = 30;				// Radius of Clusters in unclustered LAC Mode
var paraLACClusterDisableLevel = 13;		// Zoom level at which LAC clustering is Disabled
var paraLACMaxLacAmount = 250;				// Max LAC amount at which clustering is Disabled

var paraHMBlur = 40;						// Heatmap blur Parameter
var paraHMRadius = 35;						// Heatmap point-radius Parameter

var paraHMClusteredMaxDivider = 3;			// Max Intensity Divider for Heatmap in outer zoom levels
var paraHMDynamicCompareModifier = 250;		// Value alters scaling effect and threshold in inner zoom levels
var paraHMDynamicValueModifier = 0.8;		// High value increases the dynamic influence of visible cell amount in inner zoom levels

var paraSearchClusterRadius = 30;			// Cluster Radius for LAC Search
var paraSearchClusterDisableLevel = 13;		// Cluster Disable zoom level for LAC Search

var paraAJAXTimeout = 5000;

//____ Vars ____
var map;

var mlsViewLayer;
var selectedLac;

var mlsLACOutlineLayer;
var mlsLACCellLayer;
var mlsLACPolyLayer;
var mlsLACPolyHoverLayer = new L.layerGroup();
var mlsCellLayer;

var autoLoad = true;
var cellReqIsQueued = false;

//Ajax responce is only loaded, if the newest request hasn't been answered. This prevents older but slower responces of overwriting newer Data
var waitingForHash = 0;


var greenMarkerIcon = L.icon({
	iconUrl: 'leaflet/images/markerGreen.png',
	shadowUrl: 'leaflet/images/marker-shadow.png',
	iconSize: [25, 41],
	iconAnchor: [12, 41],
	popupAnchor: [1, -34],
	shadowSize: [41, 41]
});

var redMarkerIcon = L.icon({
	iconUrl: 'leaflet/images/markerRed.png',
	shadowUrl: 'leaflet/images/marker-shadow.png',
	iconSize: [25, 41],
	iconAnchor: [12, 41],
	popupAnchor: [1, -34],
	shadowSize: [41, 41]
});

var lacMarkerIcon = L.icon({
	iconUrl: 'leaflet/images/lacMarkerGreen.png',
	iconSize: [25, 27],
	iconAnchor: [12, 13],
	popupAnchor: [1, -20]
});

customMarker = L.Marker.extend({
   options: { 
      displayNumber: 1
   }
});

loadFromCookie = function()
{
	if(!(typeof Cookies.get('paraFilterLACs') == 'undefined'))
	{
		paraFilterLACs = Cookies.get('paraFilterLACs') === "true";
		paraLacFilterLimit = parseInt(Cookies.get('paraLacFilterLimit'));
		
		paraIgnoreOldData = Cookies.get('paraIgnoreOldData') === "true";
		paraIgnoreDataAge = parseInt(Cookies.get('paraIgnoreDataAge'));
		
		paraCellClusterDisableLevel = parseInt(Cookies.get('paraCellClusterDisableLevel'));
		paraCellMaxCellAmount = parseInt(Cookies.get('paraCellMaxCellAmount'));
		paraCellClusterRadius = parseInt(Cookies.get('paraCellClusterRadius'));
		
		paraClusteredClusterRadius = parseInt(Cookies.get('paraClusteredClusterRadius'));
		
		paraLACClusteredClusterRadius = parseInt(Cookies.get('paraLACClusteredClusterRadius'));
		paraLACClusterRadius = parseInt(Cookies.get('paraLACClusterRadius'));
		paraLACClusterDisableLevel = parseInt(Cookies.get('paraLACClusterDisableLevel'));
		paraLACMaxLacAmount = parseInt(Cookies.get('paraLACMaxLacAmount'));
		
		paraHMBlur = parseInt(Cookies.get('paraHMBlur'));
		paraHMRadius = parseInt(Cookies.get('paraHMRadius'));
		
		paraHMClusteredMaxDivider = parseInt(Cookies.get('paraHMClusteredMaxDivider'));
		paraHMDynamicCompareModifier = parseInt(Cookies.get('paraHMDynamicCompareModifier'));
		paraHMDynamicValueModifier = parseFloat(Cookies.get('paraHMDynamicValueModifier'));
		
		paraSearchClusterRadius = parseInt(Cookies.get('paraSearchClusterRadius'));
		paraSearchClusterDisableLevel = parseInt(Cookies.get('paraSearchClusterDisableLevel'));
	}
}

saveToCookie = function()
{
	Cookies.set('paraFilterLACs', paraFilterLACs.toString(), {expires: 2000});
	Cookies.set('paraLacFilterLimit', paraLacFilterLimit, {expires: 2000});
	
	Cookies.set('paraIgnoreOldData', paraIgnoreOldData, {expires: 2000});
	Cookies.set('paraIgnoreDataAge', paraIgnoreDataAge, {expires: 2000});
	
	Cookies.set('paraCellClusterDisableLevel', paraCellClusterDisableLevel, {expires: 2000});
	Cookies.set('paraCellMaxCellAmount', paraCellMaxCellAmount, {expires: 2000});
	Cookies.set('paraCellClusterRadius', paraCellClusterRadius, {expires: 2000});
	
	Cookies.set('paraClusteredClusterRadius', paraClusteredClusterRadius, {expires: 2000});
	
	Cookies.set('paraLACClusteredClusterRadius', paraLACClusteredClusterRadius, {expires: 2000});
	Cookies.set('paraLACClusterRadius', paraLACClusterRadius, {expires: 2000});
	Cookies.set('paraLACClusterDisableLevel', paraLACClusterDisableLevel, {expires: 2000});
	Cookies.set('paraLACMaxLacAmount', paraLACMaxLacAmount, {expires: 2000});
	
	Cookies.set('paraHMBlur', paraHMBlur, {expires: 2000});
	Cookies.set('paraHMRadius', paraHMRadius, {expires: 2000});
	
	Cookies.set('paraHMClusteredMaxDivider', paraHMClusteredMaxDivider, {expires: 2000});
	Cookies.set('paraHMDynamicCompareModifier', paraHMDynamicCompareModifier, {expires: 2000});
	Cookies.set('paraHMDynamicValueModifier', paraHMDynamicValueModifier, {expires: 2000});
	
	Cookies.set('paraSearchClusterRadius', paraSearchClusterRadius, {expires: 2000});
	Cookies.set('paraSearchClusterDisableLevel', paraSearchClusterDisableLevel, {expires: 2000});
	
	Cookies.set('paraAJAXTimeout', paraAJAXTimeout, {expires: 2000});
}

deleteCookie = function()
{
	Cookies.remove('paraFilterLACs');
	Cookies.remove('paraLacFilterLimit');
	
	Cookies.remove('paraIgnoreOldData');
	Cookies.remove('paraIgnoreDataAge');
	
	Cookies.remove('paraCellClusterDisableLevel');
	Cookies.remove('paraCellMaxCellAmount');
	Cookies.remove('paraCellClusterRadius');
	
	Cookies.remove('paraClusteredClusterRadius');
	
	Cookies.remove('paraLACClusteredClusterRadius');
	Cookies.remove('paraLACClusterRadius');
	Cookies.remove('paraLACClusterDisableLevel');
	Cookies.remove('paraLACMaxLacAmount');
	
	Cookies.remove('paraHMBlur');
	Cookies.remove('paraHMRadius');
	
	Cookies.remove('paraHMClusteredMaxDivider');
	Cookies.remove('paraHMDynamicCompareModifier');
	Cookies.remove('paraHMDynamicValueModifier');
	
	Cookies.remove('paraSearchClusterRadius');
	Cookies.remove('paraSearchClusterDisableLevel');
	
	Cookies.remove('paraAJAXTimeout');
}

// Function to create simple hash
function hashString (str){
    var hash = 0;
    if (str.length == 0) return hash;
	
    for (i = 0; i < str.length; i++) {
        char = str.charCodeAt(i);
        hash = ((hash<<5)-hash)+char;
        hash = hash & hash;
    }
    return hash;
}
	
initMap = function()
{
	var osmLayer = L.tileLayer('http://{s}.tile.openstreetmap.de/tiles/osmde/{z}/{x}/{y}.png', {
		attribution: 'Map data &copy; <a href="http://openstreetmap.org">OpenStreetMap</a> contributors, ' + 
			' <a href="http://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>',
		maxZoom: 18,
		minZoom: 2,
	});
	
	var otmLayer = L.tileLayer('http://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', {
		attribution: 'Map data &copy; <a href="http://openstreetmap.org">OpenStreetMap</a> contributors, Tiles:  &copy; <a href="http://opentopomap.org">OpenTopoMap</a>' + 
			' <a href="http://creativecommons.org/licenses/by-sa/3.0/">CC-BY-SA</a>',
		maxZoom: 18,
		minZoom: 2,
	});
	
	map = L.map('map', {
		center: [49.574, 11.0294],
		zoom: 14,
		layers: [osmLayer, otmLayer]
	});
	
	var baseMaps = {
		"OpenStreetMap": osmLayer,
		"OpenTopoMap": otmLayer
	};
	
	map.removeLayer(otmLayer);
	
	L.control.layers(baseMaps).addTo(map);
	map.addLayer(mlsLACPolyHoverLayer);
		
	new L.Control.GeoSearch({
		provider: new L.GeoSearch.Provider.OpenStreetMap(),
		position: 'topleft',
	}).addTo(map);
	
	L.control.scale({
		position: 'topleft',
		maxWidth: 150}).addTo(map);
}

searchLac = function()
{
	$("#loadingGif").show();
	$.post( 'searchCells.php', { type: 'lac', mcc: $("#sMcc").val(), mnc: $("#sMnc").val(), lac: $("#sLac").val(), radio: $("#sRadio").val()}, function( data )
	{
		autoLoad = false;
		
		$('input:radio[name="mlsModeS"]').prop('checked', false);
		$("#mlsModeDiv").buttonset("refresh");
		
		var sData = data.split("&&");
		
		if(sData.length == 1)
		{
			alert("Invalid Data Received.");
			return;
		}
		
		if(sData[0] == "ERR")
		{
			alert("No data found.");
			return;
		}
		
		if(map.hasLayer(mlsLACPolyLayer))
			map.removeLayer(mlsLACPolyLayer);
		
		if(map.hasLayer(mlsLACCellLayer))
			map.removeLayer(mlsLACCellLayer);
		
		mlsLACPolyLayer = L.layerGroup();
		mlsLACCellLayer = L.layerGroup();
		
		var cData = sData[1].split("##");
		
		var lacMarkerCluster = L.markerClusterGroup({
			disableClusteringAtZoom: paraSearchClusterDisableLevel,
			maxClusterRadius: paraSearchClusterRadius
		});		
		
		for (var i = 0; i < cData.length - 1; i++)
		{
			var cellData = cData[i].split("|");
			cellData[0] = cellData[0].replace(/\s+/g, '');
			var marker = new customMarker([parseFloat(cellData[3]), parseFloat(cellData[2]), { displayNumber: 1}])
							.setIcon(redMarkerIcon)
							.bindPopup("<center><b>" +  cellData[0] + "<br>CID: " + cellData[1] + "</b></center><br>LAC: " + 
									$("#sLac").val() + "<br>MNC: " + $("#sMnc").val() + "<br>MCC: " + $("#sMcc").val());
			lacMarkerCluster.addLayer(marker);
		}
		var polyLayer = L.geoJson(JSON.parse(sData[2])).bindPopup("<center><b>" +  cellData[0] + "</b></center><br>LAC: " + $("#sLac").val() + 
														"<br>MNC: " + $("#sMnc").val() + "<br>MCC: " + $("#sMcc").val());
		
		mlsLACCellLayer.addLayer(lacMarkerCluster);
		mlsLACPolyLayer.addLayer(polyLayer);
		
		if($("#sLACcellVis").is(":checked"))
			map.addLayer(mlsLACCellLayer);

		map.addLayer(mlsLACPolyLayer);
		map.fitBounds(lacMarkerCluster.getBounds());
		
		if(map.hasLayer(mlsViewLayer))
			map.removeLayer(mlsViewLayer);
		
		$("#loadingGif").hide();
	});
}

loadCellData = function()
{
	// If Ajax active, wait until it finishes and start a new one
	// Better version: Limit request freq. and cancel old requests (client AND server side)
	// 1: if (ajaxReq = active) -> ajaxReq.abort(); 
	// 2: newAjaxReq.start();
	// 3: Kill old request on server!?
	if(cellReqIsQueued)
	{
		if($.active > 0)
			return;
		else
			cellReqIsQueued = false;
	}
	else if($.active > 0)
	{
		cellReqIsQueued = true;
		window.setTimeout(loadCellData, 500);
		return;
	}
	
	var bounds = map.getBounds();
	var swBounds = bounds.getSouthWest();
	var neBounds = bounds.getNorthEast();
	var mapZoom = map.getZoom();
		
	var modeVar = "none";
	
	// Populate MNC List
	// Step 1: Get all MNCs
	// Step 2: Hide all entries
	// Step 3: Unhide / create all new entries in Order
	$("#loadingGif").show();
	
	var radioVar = "";
	
	if($("#GSMBox").is(":checked"))
		radioVar = "GSM|";
	if($("#UMTSBox").is(":checked"))
		radioVar += "UMTS|";
	if($("#LTEBox").is(":checked"))
		radioVar += "LTE|";
	
	if(radioVar == "")
	{
		// Nothing to load.
		$("#loadingGif").hide();
		if(map.hasLayer(mlsViewLayer))
			map.removeLayer(mlsViewLayer);
		mlsLACPolyHoverLayer.clearLayers();
		return;
	}
	
	var ageVar = 0;
	if(paraIgnoreOldData)
		ageVar = Math.floor(Date.now() / 1000) - paraIgnoreDataAge;
	
	// Create hash of args to identify AJAX responce
	var uHash = hashString(swBounds.lat + swBounds.lng + neBounds.lat + neBounds.lng + mapZoom + radioVar + "mnc" + ageVar);
	waitingForHash = uHash;
		
	$.post( 'getMLS.php', {hash: uHash, latUL: swBounds.lat, lonUL: swBounds.lng, latOR: neBounds.lat, lonOR: neBounds.lng, zoom: mapZoom, radios: radioVar, mode: "mnc", ageStamp: ageVar}, function( mncData )
	{
		var sMncData = mncData.split("&&");
		if((sMncData[0] == waitingForHash) || (waitingForHash != 0))
		{
			if(sMncData[0] == waitingForHash)
				waitingForHash = 0;
			
			if(sMncData.length == 2)
			{
				alert("Error in Response: " + mncData);
				return;
			}
			
			if(sMncData[2] == "DISABLED")
			{
				mncVar = "ALL";
				$("#mncSelectDiv").children().hide();
				$("#mncAll").prop('checked', true);
				$("#mncLaAll").show();
				$("#mncDisabledText").show("fast");
			}
			else
			{
				$("#mncDisabledText").hide("fast");
				$("#mncSelectDiv").children().hide();
				$("#mncLaAll").show();
				
				var mncVar = "";
				
				var mncs = sMncData[2].split("|");
				var lastObject;

				for (var i = 0; i < (mncs.length - 1); i++)
				{
					// Test if exists
					if($("#mncLa" + mncs[i]).length)
					{
						$("#mncLa" + mncs[i]).show();

						if($("#mnc" + mncs[i]).is(":checked"))
							mncVar += mncs[i] + "|";
					}
					else
					{
						if(typeof lastObject !== 'undefined')
							lastObject.after("<input type='checkbox' id='mnc" + mncs[i] + 
												"' class='mncSelect'/><label id='mncLa" + mncs[i] + "' for='mnc" + mncs[i] + "'>" + mncs[i] + "</label>");	
						else
							$("#mncLaAll").after("<input type='checkbox' id='mnc" + mncs[i] + 
												"' class='mncSelect'/><label id='mncLa" + mncs[i] + "' for='mnc" + mncs[i] + "'>" + mncs[i] + "</label>");
					}
					lastObject = $("#mncLa" + mncs[i]);
				}
				$("#mncSelectDiv").buttonset("refresh");
			}

			if($("#mncAll").is(":checked"))
				mncVar = "ALL";
			
			if(mncVar == "")
			{
				// Nothing to load.
				if(map.hasLayer(mlsViewLayer))
					map.removeLayer(mlsViewLayer);
				mlsLACPolyHoverLayer.clearLayers();
				$("#loadingGif").hide();
				return;
			}
			
			if($("#mlsGLac").is(":checked"))
				modeVar = "lacSort";
			
			if($("#mlsHMMode").is(":checked"))
				modeVar = "heat";
			
			var uHash = hashString(swBounds.lat + swBounds.lng + neBounds.lat + neBounds.lng + modeVar + mapZoom + radioVar + mncVar + ageVar);
			waitingForHash = uHash;
					
			$.post( 'getMLS.php', {hash: uHash, latUL: swBounds.lat, lonUL: swBounds.lng, latOR: neBounds.lat, lonOR: neBounds.lng, mode: modeVar, zoom: mapZoom, radios: radioVar, nets: mncVar, ageStamp: ageVar}, function( data )
			{
				var sData = data.split("&&");
				if(sData.length == 2)
				{
					alert("Error in Response: " + data);
					return;
				}
				
				if((sData[0] == waitingForHash) || (waitingForHash != 0))
				{
					if(sData[0] == waitingForHash)
						waitingForHash = 0;
				
					if(map.hasLayer(mlsViewLayer))
						map.removeLayer(mlsViewLayer);
					mlsLACPolyHoverLayer.clearLayers();
				
					mlsViewLayer = L.layerGroup();
					
					var mlsMarkerCluster;
					var cData = sData[2].split("##");
					
					if(sData[1] == "cell")
					{
						var disableLevel = paraCellClusterDisableLevel;
						if(cData.length < paraCellMaxCellAmount)
							disableLevel = map.getZoom();

						mlsMarkerCluster = L.markerClusterGroup({
							iconCreateFunction: function (cluster) {
								markers = cluster.getAllChildMarkers();
								var number = 0;
								for(var i = 0; i < markers.length; i++)
									number += parseInt(markers[i].options.displayNumber);
								var c = ' marker-cluster-';
								if (number < 5) {
									c += 'small';
								} else if (number < 15) {
									c += 'medium';
								} else {
									c += 'large';
								}
								return new L.DivIcon({ html: '<div><span>' + number + '</span></div>', className: 'marker-cluster' + c, iconSize: new L.Point(40, 40) });
							},
							maxClusterRadius: paraCellClusterRadius,
							singleMarkerMode: false,
							spiderfyOnMaxZoom: true,
							showCoverageOnHover: true,
							zoomToBoundsOnClick: true,
							disableClusteringAtZoom: disableLevel
						});

						for (var i = 0; i < (cData.length - 1); i++)
						{
							var clusterData = cData[i].split("|");
							
							var marker = new customMarker([parseFloat(clusterData[6]), parseFloat(clusterData[5])], {displayNumber: 1})
								.bindPopup("<center><b>" +  clusterData[0] + "</b></center><br>MCC: " + clusterData[1] + 
										"<br>MNC: " + clusterData[2] + "<br>LAC: " + clusterData[3] + 
										"<br>CID: " + clusterData[4]).setIcon(greenMarkerIcon);
							mlsMarkerCluster.addLayer(marker);
						}
						
					}else if (sData[1] == "cluster" || sData[1] == "lacSortClustered")
					{	
						var maxCRad = paraClusteredClusterRadius;
						if(sData[1] == "lacSortClustered")
							maxCRad = paraLACClusteredClusterRadius;
						
						mlsMarkerCluster = L.markerClusterGroup({
							iconCreateFunction: function (cluster) {
								markers = cluster.getAllChildMarkers();
								var number = 0;
								for(var i = 0; i < markers.length; i++)
									number += parseInt(markers[i].options.displayNumber);
								var c = ' marker-cluster-';
								if (number < Math.pow(2, (20-mapZoom))) {
									c += 'small';
								} else if (number < Math.pow(2, (22-mapZoom))) {
									c += 'medium';
								} else {
									c += 'large';
								}
								return new L.DivIcon({ html: '<div><span>' + number + '</span></div>', className: 'marker-cluster' + c, iconSize: new L.Point(40, 40) });
							},
							maxClusterRadius: maxCRad,
							singleMarkerMode: true,
							spiderfyOnMaxZoom: false,
							showCoverageOnHover: false,
							zoomToBoundsOnClick: false,
						});
						
						for (var i = 0; i < (cData.length - 1); i++)
						{
							var clusterData = cData[i].split("|");
							if(clusterData[2] != 0)
							{
								var marker = new customMarker([parseFloat(clusterData[0]), parseFloat(clusterData[1])], {displayNumber: parseInt(clusterData[2])});
								mlsMarkerCluster.addLayer(marker);
							}
						}
					}else if (sData[1] == "lacSort")
					{				
						var disableLevel = paraLACClusterDisableLevel;
						if(cData.length < paraLACMaxLacAmount)
							disableLevel = map.getZoom() - 3;
						
						mlsMarkerCluster = L.markerClusterGroup({
							iconCreateFunction: function (cluster) {
								markers = cluster.getAllChildMarkers();
								var number = 0;
								for(var i = 0; i < markers.length; i++)
									number += parseInt(markers[i].options.displayNumber);
								var c = ' marker-cluster-';
								if (number < (1 * 20)) {
									c += 'small';
								} else if (number < (10 * 40)) {
									c += 'medium';
								} else {
									c += 'large';
								}
								return new L.DivIcon({ html: '<div><span>' + number + '</span></div>', className: 'marker-cluster' + c, iconSize: new L.Point(40, 40) });
							},
							maxClusterRadius: paraLACClusterRadius,
							singleMarkerMode: false,
							spiderfyOnMaxZoom: false,
							showCoverageOnHover: true,
							zoomToBoundsOnClick: true,
							disableClusteringAtZoom: disableLevel
						});
						
						for (var i = 0; i < (cData.length - 1); i++)
						{
							var clusterData = cData[i].split("|");
							if(clusterData[2] != 0)
							{
								if(!paraFilterLACs || (parseInt(clusterData[4]) >= paraLacFilterLimit))
								{
									clusterData[1] = clusterData[1].replace(/\s+/g, '');
									var polyLayer = L.geoJson(JSON.parse(clusterData[7])).bindPopup("<center><b>" +  clusterData[1] + 
													"</b></center><br>LAC: " + clusterData[0] + 
													"<br>MNC: " + clusterData[2] + 
													"<br>MCC: " + clusterData[3]);
									
									var marker = new customMarker([parseFloat(clusterData[6]), parseFloat(clusterData[5])], 
																	{displayNumber: parseInt(clusterData[4]), 
																	lacPoly: polyLayer, lac: clusterData[0],
																	mnc: clusterData[2], mcc: clusterData[3], radio: clusterData[1]})
												.bindPopup("<center><b>LAC: " + clusterData[0] + "</b></br>Size: " + clusterData[4] + "</center>")
												.on('click', function(e) {
													if(map.hasLayer(mlsLACOutlineLayer))
														map.removeLayer(mlsLACOutlineLayer);
													
													if(this.options.lac != selectedLac)
													{
														mlsLACOutlineLayer = this.options.lacPoly;
														map.addLayer(mlsLACOutlineLayer);
														selectedLac = this.options.lac;
													}else
														selectedLac = undefined;
													})
												.on('mouseover', function (e) {
													mlsLACPolyHoverLayer.clearLayers();
													mlsLACPolyHoverLayer.addLayer(L.geoJson(this.options.lacPoly.toGeoJSON()));
													this.openPopup();
													})
												.on('mouseout', function (e) {
													mlsLACPolyHoverLayer.clearLayers();
													this.closePopup();
													})
												.on('dblclick', function(e) {
													mlsLACPolyHoverLayer.clearLayers();
													if(map.hasLayer(mlsLACOutlineLayer))
														map.removeLayer(mlsLACOutlineLayer);
													mlsLACOutlineLayer = this.options.lacPoly;
													map.addLayer(mlsLACOutlineLayer);
													selectedLac = this.options.lac;
													
													// Zoom to LAC (-> LAC Search)
													$("#sMcc").val(this.options.mcc);
													$("#sMnc").val(this.options.mnc);
													$("#sLac").val(this.options.lac);
													$("#sRadio").val(this.options.radio);
													$("#sId").val("");
													searchLac();
												})
												.setIcon(lacMarkerIcon);
									mlsMarkerCluster.addLayer(marker);
								}
							}
						}
					}else if (sData[1] == "heat")
					{	
						
						var latlngArray = new Array(cData.length - 1);
						var maxValue = 1;
							
						for (var i = 0; i < (cData.length - 1); i++)
						{
							var clusterData = cData[i].split("|");	
							var value = parseFloat(clusterData[2]);
							latlngArray[i] = [parseFloat(clusterData[1]), parseFloat(clusterData[0]), value];
							if(value > maxValue)
								maxValue = value;
						}
						
						// Dynamic Intensity Scaling
						var mZoom = mapZoom;
						if(maxValue == 1)
						{
							
							var compVar = paraHMDynamicCompareModifier*Math.pow(18-mZoom, 2);
							if(cData.length < compVar)
								maxValue = (1-paraHMDynamicValueModifier) + paraHMDynamicValueModifier*cData.length/compVar;
							mZoom = map.getMaxZoom();
						}
						else
							maxValue /= paraHMClusteredMaxDivider;
						
						var heatLayer = L.heatLayer(latlngArray, {max: maxValue, blur: paraHMBlur, radius: paraHMRadius, maxZoom: mZoom});
						mlsViewLayer.addLayer(heatLayer);
						
					}else alert("Error in Response: " + data);
					
					if(typeof mlsMarkerCluster !== 'undefined')
						mlsViewLayer.addLayer(mlsMarkerCluster);
					map.addLayer(mlsViewLayer);
					
					$("#loadingGif").hide();
				}
			});
		}
	});
}

setParams = function()
{
	// Save new Params
	if($("#SETignoreOldData").is(':checked'))
		paraIgnoreOldData = true;
	else
		paraIgnoreOldData = false;
	// Do some UNIX timestamp conversion
	var ageStr = $("#SEToldDataThreshold").val();
	paraIgnoreDataAge = parseInt(parseInt(ageStr)) * 2628000;
	
	paraAJAXTimeout = parseFloat($("#SETajaxTimeout").val());
	paraSearchClusterRadius = parseFloat($("#SETsearchClusterRadius").val());
	paraSearchClusterDisableLevel = parseInt($("#SETsearchClusterDisableLevel").val());
	
	paraCellClusterDisableLevel = parseInt($("#SETcellClusterDisableLevel").val());
	paraCellMaxCellAmount = parseFloat($("#SETcellMaxCellAmount").val());
	paraCellClusterRadius = parseFloat($("#SETcellClusterRadius").val());
	paraClusteredClusterRadius = parseFloat($("#SETclusteredClusterRadius").val());
	
	if($("#SETfilterLACs").is(':checked'))
		paraFilterLACs = true;
	else
		paraFilterLACs = false;
	
	paraLacFilterLimit = parseFloat($("#SETlacFilterLimit").val());
	
	paraLACClusterRadius = parseFloat($("#SETLACClusterRadius").val());
	paraLACClusteredClusterRadius = parseFloat($("#SETLACClusteredClusterRadius").val());
	paraLACClusterDisableLevel = parseInt($("#SETLACClusterDisableLevel").val());
	paraLACMaxLacAmount = parseFloat($("#SETLACMaxLacAmount").val());
	
	paraHMClusteredMaxDivider = parseFloat($("#SETHMClusteredMaxDivider").val());
	paraHMDynamicCompareModifier = parseFloat($("#SETHMDynamicCompareModifier").val());
	paraHMDynamicValueModifier = parseFloat($("#SETHMDynamicValueModifier").val());
	
	paraHMBlur = parseFloat($("#SETHMBlur").val());
	paraHMRadius = parseFloat($("#SETHMRadius").val());

	saveToCookie();
}

$(document).ready(function()
{
	loadFromCookie();
	
	$("#searchDiv").hide();
	
	$("#mlsModeDiv").buttonset();
	$("#typeSelectDiv").buttonset();
	$("#mncSelectDiv").buttonset();
	
	$("#settingsBtn").button({
		icons: {secondary: "ui-icon-newwin"}
	});
	
	$("#sBtn").button({
		icons: {secondary: "ui-icon-newwin"}
	});
	
	$("#mncDisabledText").hide();
	$("#loadingGif").hide();
	
	$.ajaxSetup({
		timeout: paraAJAXTimeout,
		error: function(x, t, m) {
			if(t==="timeout") {
				alert("Server took to long to respond. May be overloaded?");
			} else {
				// Some other Error
			}
			waitingForHash = 0;
			$("#loadingGif").hide();
		}
	});
	
	// Load Builddate
	$.post('getInfo.php', {para: 'DB_DATE_STRING'}, function(data){
		$("#buildInfo").append(data);
	});
	
	// Search Dialog
	$("#searchDialog").dialog({
		autoOpen: false,
		width: 218,
		maxWidth: 218,
		buttons: [
					{
						text: "Search",
						click: function() {
							if($("#sId").val() == "")
								searchLac();
							else
							{
								$.post( 'searchCells.php', { type: 'cell', mcc: $("#sMcc").val(), mnc: $("#sMnc").val()
													   , lac: $("#sLac").val(), cid: $("#sId").val(), radio: $("#sRadio").val()}, function( data )
								{
									if(data == "MULTIPLE")
									{
										alert("Multiple Cells Found.");
										return;
									} else if(data == "NONE")
									{
										alert("No Cell Found.");
										return;
									}
									
									if(map.hasLayer(mlsCellLayer))
										map.removeLayer(mlsCellLayer);
										
									var lonlat = data.split("|");
									
									if(lonlat.length == 1)
									{
										alert("Invalid Data Received. Database Error?");
										return;
									}
									
									mlsCellLayer = L.marker([parseFloat(lonlat[1]), parseFloat(lonlat[0])]);
									
									map.addLayer(mlsCellLayer);
									map.panTo(new L.LatLng(parseFloat(lonlat[1]), parseFloat(lonlat[0])));
									map.setZoom(15);
								});
							}
						}
					},
					{
						text: "Close",
						click: function() {
							$("#searchDialog").dialog("close");
						}
					}
				],
				open: function(){
					// Load UI
					$("#sRadio").selectmenu({width: 80});
					$("#sMCC").addClass("ui-widget ui-widget-content ui-corner-all");
					$("#sMNC").addClass("ui-widget ui-widget-content ui-corner-all");
					$("#sLAC").addClass("ui-widget ui-widget-content ui-corner-all");
					$("#sID").addClass("ui-widget ui-widget-content ui-corner-all");
					$("#sLACcellVis").button();

					$('.searchTextBox').on('keyup', function(event) {
						if($.isNumeric(this.value) === false)
							this.value = this.value.slice(0,-1);
					});				
				}
	});
		
	// Settings Dialog
	$("#settingsDialog").dialog({
		autoOpen: false,
		width: 500,
		maxWidth: 500,
		buttons: [
					{
						text: "Ok",
						click: function() {
							setParams();
							if(autoLoad)
								loadCellData();
							$( this ).dialog( "close" );
						}
					},
					{
						text: "Apply",
						click: function() {				
							setParams();

							if(autoLoad)
								loadCellData();
						}
					},
					{
						
						text: "Restore defaults",
						click: function() {
							deleteCookie();
							location.reload();
						},
					}
				],
				open: function(){
					// Load UI
					$("#SETignoreOldData").button();
					$("#SEToldDataThreshold").addClass("ui-widget ui-widget-content ui-corner-all");
					$("#SETajaxTimeout").selectmenu({width: 140});
					$("#SETsearchClusterRadius").addClass("ui-widget ui-widget-content ui-corner-all");
					$("#SETsearchClusterDisableLevel").selectmenu({width: 140});
					
					$("#SETcellClusterDisableLevel").selectmenu({width: 140});
					$("#SETcellMaxCellAmount").addClass("ui-widget ui-widget-content ui-corner-all");
					$("#SETcellClusterRadius").addClass("ui-widget ui-widget-content ui-corner-all");
					$("#SETclusteredClusterRadius").addClass("ui-widget ui-widget-content ui-corner-all");
					
					$("#SETfilterLACs").button();
					$("#SETlacFilterLimit").addClass("ui-widget ui-widget-content ui-corner-all");
					
					$("#SETparaLACClusterRadius").addClass("ui-widget ui-widget-content ui-corner-all");
					$("#SETLACClusteredClusterRadius").addClass("ui-widget ui-widget-content ui-corner-all");
					$("#SETLACClusterDisableLevel").selectmenu({width: 140});
					$("#SETLACMaxCLacAmount").addClass("ui-widget ui-widget-content ui-corner-all");
					
					$("#SETHMClusteredMaxDivider").addClass("ui-widget ui-widget-content ui-corner-all");
					$("#SETHMDynamicCompareModifier").addClass("ui-widget ui-widget-content ui-corner-all");
					$("#SETHMDynamicValueModifier").selectmenu({width: 140});
					
					$("#SETHMBlur").addClass("ui-widget ui-widget-content ui-corner-all");
					$("#SETHMRadius").addClass("ui-widget ui-widget-content ui-corner-all");
					
					// Populate UI
					$("#SETignoreOldData").attr("checked", paraIgnoreOldData).button("refresh");
					var months = Math.floor(paraIgnoreDataAge / 2628000);
					$("#SEToldDataThreshold").val(months);
					
					$("#SETajaxTimeout").val(paraAJAXTimeout).selectmenu("refresh");
					$("#SETsearchClusterRadius").val(paraSearchClusterRadius);
					$("#SETsearchClusterDisableLevel").val(paraSearchClusterDisableLevel).selectmenu("refresh");
					
					$("#SETcellClusterDisableLevel").val(paraCellClusterDisableLevel).selectmenu("refresh");
					$("#SETcellMaxCellAmount").val(paraCellMaxCellAmount);
					$("#SETcellClusterRadius").val(paraCellClusterRadius);
					$("#SETclusteredClusterRadius").val(paraClusteredClusterRadius);
					
					$("#SETfilterLACs").attr("checked", paraFilterLACs).button("refresh");
					$("#SETlacFilterLimit").val(paraLacFilterLimit);
					
					$("#SETLACClusterRadius").val(paraLACClusterRadius);
					$("#SETLACClusteredClusterRadius").val(paraLACClusteredClusterRadius);
					$("#SETLACClusterDisableLevel").val(paraLACClusterDisableLevel).selectmenu("refresh");
					$("#SETLACMaxLacAmount").val(paraLACMaxLacAmount);
					
					$("#SETHMClusteredMaxDivider").val(paraHMClusteredMaxDivider);
					$("#SETHMDynamicCompareModifier").val(paraHMDynamicCompareModifier);
					$("#SETHMDynamicValueModifier").val(paraHMDynamicValueModifier).selectmenu("refresh");
					
					$("#SETHMBlur").val(paraHMBlur);
					$("#SETHMRadius").val(paraHMRadius);
					
					$('.settingsTextBox').on('keyup', function(event) {
						if($.isNumeric(this.value) === false)
							this.value = this.value.slice(0,-1);
					});				
				}
	});
	
	$("#settingsContainer").accordion({
      heightStyle: "content"
    });
	
	$(document).tooltip({
		tooltipClass: "tooltipClass"
	});

	initMap();	
	
	if(autoLoad)
		loadCellData();

	map.on('dragend', function(e) {
		if(autoLoad)
			loadCellData();
	});

	map.on('zoomend', function(e) {
		if(autoLoad)
			loadCellData();
	});
	
	$("input[name='radioSelect']").click(function() {
		if(autoLoad)
			loadCellData();
	});
	
	$("#mncSelectDiv").on( "click", ".mncSelect", function() {
		//uncheck "Show all" if something else is clicked
		if($(this).attr("id") == "mncAll")
			loadCellData();
		else
		{
			$("#mncAll").prop('checked', false);
			loadCellData();
		}
	});
	
	$('input:radio[name="mlsModeS"]').change(function(){
		autoLoad = true;
		if(autoLoad)
			loadCellData();
		if(map.hasLayer(mlsLACOutlineLayer))
			map.removeLayer(mlsLACOutlineLayer);
		if(map.hasLayer(mlsLACCellLayer))
			map.removeLayer(mlsLACCellLayer);
		if(map.hasLayer(mlsLACPolyLayer))
			map.removeLayer(mlsLACPolyLayer);
	});
	
	$("#settingsBtn").click(function(){
		$("#settingsDialog").dialog("open");
	});
	
	$("#sBtn").click(function(){
		$("#searchDialog").dialog("open");
	});
	
	$("#sLACcellVis").click(function(){
		if($("#sLACcellVis").is(":checked"))
		{
			if(map.hasLayer(mlsLACPolyLayer))
				map.addLayer(mlsLACCellLayer);
		}else
		{
			if(map.hasLayer(mlsLACCellLayer))
				map.removeLayer(mlsLACCellLayer);
		}
	});
});
