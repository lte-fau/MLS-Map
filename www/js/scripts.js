/* Copyright (C) 2016  Lehrstuhl für Technische Elektronik, Friedrich-Alexander-Universität Erlangen-Nürnberg */
/* https://github.com/lte-fau/MLS-Map/blob/master/LICENSE */
//____ Default Settings ____
var paraFilterLACs = true; 					// Remove small LACs
var paraLacFilterLimit = 10;				// Minimum Location Area size to not be filtered

var paraIgnoreOldData = false;				// Ignore Cell Data that hasn't been modified in some time
var paraIgnoreDataAge = 7776000;			// Max Age (90d in seconds)

var paraDataSource = "ocid";				// "mls" or "ocid", database to use

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
var osmLayer;
var osmLayer;

var cellViewLayer;									// Main data layer 
var selectedLac;

var cvLACOutlineLayer;
var cvLACPolyHoverLayer = new L.layerGroup();

var sLACCellLayer;									// LAC search cell layer
var sLACPolyLayer;									// Lac search poly layer
var sCellLayer;										// Cell search layer

var measLayer;										// Measurement data layer

var autoLoad = true;
var cellReqIsQueued = false;
var timeoutHandle;

var isNotifying = false;
var notificationTimeoutHandle;

//Ajax responce is only loaded if the newest request hasn't been answered. This prevents older but slower responces of overwriting newer Data
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

var blueMarkerClusterIcon = L.icon({
	iconUrl: 'leaflet/images/markerBlueCluster.png',
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


loadFromCookie = function()
{
	if(!(typeof Cookies.get('paraFilterLACs') == 'undefined'))
	{
		paraFilterLACs = Cookies.get('paraFilterLACs') === "true";
		paraLacFilterLimit = parseInt(Cookies.get('paraLacFilterLimit'));
		
		paraIgnoreOldData = Cookies.get('paraIgnoreOldData') === "true";
		paraIgnoreDataAge = parseInt(Cookies.get('paraIgnoreDataAge'));
		
		paraDataSource = Cookies.get('paraDataSource');
		
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
	
	Cookies.set('paraDataSource', paraDataSource, {expires: 2000});
	
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
	
	Cookies.remove('paraDataSource');
	
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
function hashString(str)
{
    var hash = 0;
    if (str.length == 0) return hash;
	
    for (i = 0; i < str.length; i++) {
        char = str.charCodeAt(i);
        hash = ((hash<<5)-hash)+char;
        hash = hash & hash;
    }
    return hash;
}

function notify(str)
{
	if(isNotifying && typeof(str) === 'undefined')
	{
		isNotifying = false;
		$("#notificationDiv").hide("slow");
	}
	else
	{
		//if(!isNotifying) // Leaflet calling dblClick event twice?
			$("#notificationDiv").empty();
		
		$("#notificationDiv").append("<p>" + str + "</p>");
		$("#notificationDiv").show("slow");
		
		isNotifying = true;
		window.clearTimeout(notificationTimeoutHandle);
		notificationTimeoutHandle = window.setTimeout(notify, 4000);
		$("#loadingGif").hide();
	}
}

function setUrlParams(sMode, sMcc, sMnc, sLac, sRadio, sCid)
{
	switch(sMode){
		case "s":
			if(sCid ===  null)
				var params = {m: sMode, c: sMcc, n: sMnc, a: sLac, r: sRadio};
			else
				var params = {m: sMode, c: sMcc, n: sMnc, a: sLac, r: sRadio, i: sCid};
			break;
		case "m":
			var params = {m: sMode, c: sMcc, n: sMnc, a: sLac, r: sRadio, i: sCid};
			break;
		case "norm":
		case "lacSort":
		case "heat":
			var mCenter = map.getCenter();
			var params = {m: sMode, z: map.getZoom(), la: (mCenter.lat).toFixed(5), lo: (mCenter.lng).toFixed(5), n: sMnc, r: sRadio};
			break;
		default:
	}
	
	var str = "?" + jQuery.param(params);
	window.history.replaceState('', '', str);
}

function stopCellView()
{
	autoLoad = false;
	
	$('input:radio[name="cvModeS"]').prop('checked', false);
	$("#cvModeDiv :input").checkboxradio("refresh");
	$("#mncDiv").hide("fast");
	clearMap();
}

function clearMap()
{	
	if(map.hasLayer(cellViewLayer))
		map.removeLayer(cellViewLayer);
	cvLACPolyHoverLayer.clearLayers();
	
	if(map.hasLayer(cvLACOutlineLayer) && !autoLoad)
		map.removeLayer(cvLACOutlineLayer);
	
	if(map.hasLayer(sLACCellLayer))
		map.removeLayer(sLACCellLayer);
	if(map.hasLayer(sLACPolyLayer))
		map.removeLayer(sLACPolyLayer);
	if(map.hasLayer(sCellLayer))
		map.removeLayer(sCellLayer);
	
	if(map.hasLayer(measLayer))
		map.removeLayer(measLayer);
	
	$("#informationText").hide("fast");
}

function search()
{
	if($("#sId").val() == "")
		loadLacData($("#sMcc").val(), $("#sMnc").val(), $("#sLac").val(), $("#sRadio").val());
	else
	{
		$.post( 'searchCells.php', { type: 'cell', mcc: $("#sMcc").val(), mnc: $("#sMnc").val()
							   , lac: $("#sLac").val(), cid: $("#sId").val(), radio: $("#sRadio").val(), dataSource: paraDataSource, ageStamp: 0}, function( data )
		{
			if(data == "MULTIPLE")
			{
				notify("Multiple Cells Found.");
				return;
			} else if(data == "NONE")
			{
				notify("No Cell Found.");
				return;
			}
				
			var lonlatP = data.split("|");
			
			if(lonlatP.length < 3)
			{
				notify("Invalid Data Received. Database Error?");
				return;
			}
			
			stopCellView();
			setUrlParams("s", $("#sMcc").val(), $("#sMnc").val(), $("#sLac").val(), $("#sRadio").val(), $("#sId").val());
			
			sCellLayer = getCellMarker(parseFloat(lonlatP[1]), parseFloat(lonlatP[0]), $("#sMcc").val(), $("#sMnc").val(), $("#sLac").val(), $("#sId").val(), $("#sRadio").val(), lonlatP[2]);
			map.addLayer(sCellLayer);
			
			map.panTo(sCellLayer.getLatLng());
			map.setZoom(14);
		});
	}
}

function loadLacData(mcc, mnc, area, radio)
{
	$("#loadingGif").show();
	
	
	var ageVar = 0;
	if(paraIgnoreOldData)
		ageVar = Math.floor(Date.now() / 1000) - paraIgnoreDataAge;

	$.post( 'searchCells.php', { type: 'lac', mcc: mcc, mnc: mnc, lac: area, radio: radio, ageStamp: ageVar, dataSource: paraDataSource}, function(data)
	{
		var sData = data.split("&&");
		
		if(sData[0] == "ERR")
		{
			notify("No data found.");
			return;
		}
		
		if(sData.length <= 2)
		{
			notify("Invalid Data Received.");
			return;
		}
		
		stopCellView();
		setUrlParams("s", mcc, mnc, area, radio, null);
		
		sLACPolyLayer = L.layerGroup();
		sLACCellLayer = L.layerGroup();
		
		var cData = sData[1].split("##");
		
		var lacMarkerCluster = L.markerClusterGroup({
			disableClusteringAtZoom: paraSearchClusterDisableLevel,
			maxClusterRadius: paraSearchClusterRadius
		});		
		
		for (var i = 0; i < cData.length - 1; i++)
		{
			var cellData = cData[i].split("|");
			var marker = getCellMarker(parseFloat(cellData[2]), parseFloat(cellData[1]), mcc, mnc, area, cellData[0], radio, parseInt(cellData[3]), parseInt(cellData[4]));
			lacMarkerCluster.addLayer(marker);
		}

		var lacData = sData[3].split("|");
		if(sData[2] != "") // If sData[2] is empty, lacData[0] will be too.
		{
			var polyLayer2 = L.geoJson(JSON.parse(lacData[0]));
			sLACPolyLayer.addLayer(polyLayer2);
			
			var polyLayer = L.geoJson(JSON.parse(sData[2])).bindPopup("<center><b>" +  radio + "</b></center><br>LAC: " + area + "<br>MNC: " + mnc + "<br>MCC: " + mcc);
			sLACPolyLayer.addLayer(polyLayer);
				
			map.fitBounds(polyLayer2.getBounds());
		} else
		{
			notify("Data not trustworthy. No Polygon Available.");
			map.fitBounds(lacMarkerCluster.getBounds());
		}
		
		sLACCellLayer.addLayer(lacMarkerCluster);
		
		$("#informationText").empty();
		$("#informationText").append("<strong>LAC Information:</strong></br>");
		$("#informationText").append("<center><b>" + radio + "</b></center><br>MCC: " + mcc + 
			"<br>MNC: " + mnc + "<br>LAC: " + area + "<br>Size: " + lacData[1] + "<br>Untrustworthy cells: " + lacData[2]);
		$("#informationText").show("fast");
		
		
		if($("#sLACcellVis").is(":checked"))
			map.addLayer(sLACCellLayer);

		map.addLayer(sLACPolyLayer);
		
		
		$("#loadingGif").hide();
	});
}

function getCellProblemText(problem)
{
	switch(problem)
	{
		case 0:
			return "";
			break;
		case 1:
			return "<center><b>Cell location suspicious: <br> Not in correct country.</b></center><br>";
			break;
		case 2:
			return "<center><b>Cell location suspicious: <br> To far from location area.</b></center><br>";
			break;
		default:
			return "";
	}
}

function getCellMarker(lon, lat, mcc, mnc, area, cid, radio, updateDate, problem)
{
	var cStatus = getCellProblemText(problem);
	
	var date = new Date(0);
	date.setUTCSeconds(updateDate);
	var updateText = date.toLocaleString();
	
	var marker = new L.Marker([lon, lat], {displayNumber: 1, mcc: mcc, net: mnc, area: area, cid: cid, radio: radio, updateText: updateText, problemText: cStatus})
		.bindPopup(cStatus + "<center><b>" +  radio + "</b></center><br>MCC: " + mcc + "<br>MNC: " + mnc + 
					"<br>LAC: " + area + "<br>CID: " + cid + "<br>Last Update:<br>" + updateText).setIcon(greenMarkerIcon)
		.on('dblclick', function(e) {
			loadMeasData(this);
		});

	return marker;
}

function loadMeasData(mkr)
{
	if (paraDataSource == "ocid")
	{
		var mCord = mkr.getLatLng();
		$("#loadingGif").show();
		$.post( 'getMeasData.php', {mcc: mkr.options.mcc, net: mkr.options.net, area: mkr.options.area, cid: mkr.options.cid, radio: mkr.options.radio}, function(measData)
		{
			var mData = measData.split("&&");
			if(mData.length == 2)
			{
				notify("No Data.");
				return;
			}
			
			stopCellView();
			
			$("#informationText").empty();
			$("#informationText").append("<strong>Cell Information:</strong></br>");
			$("#informationText").append("<center><b>" +  mkr.options.radio + "</b></center><br>MCC: " + mkr.options.mcc + 
				"<br>MNC: " + mkr.options.net + "<br>LAC: " + mkr.options.area + "<br>CID: " + mkr.options.cid + "<br>Last Update:<br>" + mkr.options.updateText + "<br> " + "<br> " + mkr.options.problemText);
			$("#informationText").show("fast");
			
			measLayer = L.featureGroup();
			var measCluster = L.markerClusterGroup({
				iconCreateFunction: function (cluster) {
								var markers = cluster.getAllChildMarkers();
								var nOpacity = 0;
								for (var i = 0; i < cluster.getChildCount(); i++)
									nOpacity += markers[i].options.opacity;
								nOpacity /= cluster.getChildCount();
								cluster.options.opacity = nOpacity;
								return blueMarkerClusterIcon;
							},
					maxClusterRadius: 4,
					singleMarkerMode: false,
					spiderfyOnMaxZoom: true,
					showCoverageOnHover: true,
					zoomToBoundsOnClick: false,
					disableClusteringAtZoom: 15
				}).on('clusterclick', function (a) {
					a.layer.spiderfy();
				});
			
			for (var i = 2; i < (mData.length - 1); i++)
			{
				var sMeasData = mData[i].split("|");
				
				var lowPower = -105;
				var highPower = -75;
				var diff = highPower - lowPower;
				
				var hue = (parseInt(sMeasData[2]) - lowPower) / diff;
				if(hue > 1) hue = 1;
				if(hue < 0) hue = 0;
				
				var opacity = hue + 0.3;
				if(opacity > 1) opacity = 1;
				opacity = opacity * 0.9;
				
				var marker = new L.Marker([parseFloat(sMeasData[1]), parseFloat(sMeasData[0])], {title: (parseInt(sMeasData[2]) + " dBm"), opacity: opacity});

				hue = hue * 135;
				var conPoly = L.polyline(new Array(mCord, marker.getLatLng()), {color: 'hsl('+hue+',100%,50%)', opacity: opacity * 0.15});
				measLayer.addLayer(conPoly);
				measCluster.addLayer(marker);
			}
			
			mkr.setIcon(redMarkerIcon);
			measLayer.addLayer(mkr);
			measLayer.addLayer(measCluster);
			map.addLayer(measLayer);
			map.fitBounds(measLayer.getBounds());
			
			setUrlParams("m", mkr.options.mcc, mkr.options.net, mkr.options.area, mkr.options.radio, mkr.options.cid)
			
			$("#loadingGif").hide();
		})
	}else
		notify("No Measurement data available for MLS.");
}

function loadCellData()
{
	// Queue upto one request, retry later if request is active
	if(cellReqIsQueued)
	{
		if($.active > 0)
		{
			window.clearTimeout(timeoutHandle);
			timeoutHandle = window.setTimeout(loadCellData, 500);
			return;
		}
		else
			cellReqIsQueued = false;
	}
	else if($.active > 0)
	{
		cellReqIsQueued = true;
		window.clearTimeout(timeoutHandle);
		timeoutHandle = window.setTimeout(loadCellData, 500);
		return;
	}
	
	$("#loadingGif").show();
	
	var bounds = map.getBounds();
	var swBounds = bounds.getSouthWest();
	var neBounds = bounds.getNorthEast();
	var mapZoom = map.getZoom();
	
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
		clearMap();
		$("#loadingGif").hide();
		return;
	}
	
	var ageVar = 0;
	if(paraIgnoreOldData)
		ageVar = Math.floor(Date.now() / 1000) - paraIgnoreDataAge;
	
	// Populate MNC List
	// Step 1: Get all MNCs
	// Step 2: Hide all entries
	// Step 3: Unhide / create all new entries in Order
	
	// Create hash of args to identify AJAX responce
	waitingForHash = hashString(swBounds.lat + swBounds.lng + neBounds.lat + neBounds.lng + mapZoom + radioVar + "mnc" + ageVar + paraDataSource);
	
	$.post( 'getData.php', {hash: waitingForHash, latUL: swBounds.lat, lonUL: swBounds.lng, latOR: neBounds.lat, lonOR: neBounds.lng, zoom: mapZoom, radios: radioVar, nets: "mnc", mode: "mnc", ageStamp: ageVar, dataSource: paraDataSource}, function( mncData )
	{
		var sMncData = mncData.split("&&");
		if((sMncData[0] == waitingForHash) || (waitingForHash != 0))
		{
			if(sMncData[0] == waitingForHash)
				waitingForHash = 0;
			
			if(sMncData.length == 2)
			{
				notify("Error in Response: " + mncData);
				return;
			}
			
			if(sMncData[2] == "DISABLED")
			{
				mncVar = "ALL";
				$("#mncSelectDiv").hide("fast");
				$("#mncAll").prop('checked', true);
				$("#mncDisabledText").show("fast");
			}
			else
			{
				$("#mncDisabledText").hide("fast");
				$("#mncSelectDiv").children().hide();
				$("#mncLaAll").show();
				$("#mncSelectDiv").show("fast");
				
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
				
				$("#mncSelectDiv :input").checkboxradio({
					icon: false
				}).checkboxradio("refresh");
			}

			if($("#mncAll").is(":checked"))
				mncVar = "ALL";
			
			if(mncVar == "")
			{
				// Nothing to load.
				clearMap();
				$("#loadingGif").hide();
				return;
			}
			
			var modeVar = "norm";
			if($("#cvGLac").is(":checked"))
				modeVar = "lacSort";
			
			if($("#cvHMMode").is(":checked"))
				modeVar = "heat";
			
			setUrlParams(modeVar, null, mncVar, null, radioVar, null );
			
			waitingForHash = hashString(swBounds.lat + swBounds.lng + neBounds.lat + neBounds.lng + modeVar + mapZoom + radioVar + mncVar + ageVar + paraDataSource);
					
			$.post( 'getData.php', {hash: waitingForHash, latUL: swBounds.lat, lonUL: swBounds.lng, latOR: neBounds.lat, lonOR: neBounds.lng, mode: modeVar, zoom: mapZoom, radios: radioVar, nets: mncVar, ageStamp: ageVar, dataSource: paraDataSource}, function( data )
			{
				var sData = data.split("&&");
				if(sData.length == 2)
				{
					notify("Error in Response: " + data);
					return;
				}
				
				if((sData[0] == waitingForHash) || (waitingForHash != 0))
				{
					if(sData[0] == waitingForHash)
						waitingForHash = 0;
				
					clearMap();
				
					cellViewLayer = L.layerGroup();
					
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
								if (number < 5)
									c += 'small';
								else if (number < 15)
									c += 'medium';
								else
									c += 'large';
								
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
							var marker = getCellMarker(parseFloat(clusterData[6]), parseFloat(clusterData[5]), clusterData[1], clusterData[2], clusterData[3], clusterData[4], clusterData[0], parseInt(clusterData[7]), parseInt(clusterData[8]));
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
								if (number < Math.pow(2, (20-mapZoom)))
									c += 'small';
								else if (number < Math.pow(2, (22-mapZoom)))
									c += 'medium';
								else
									c += 'large';
			
								var mWidth = 40;
								if (number < 1000)
									c += ' marker-cluster-s';
								else if (number < 100000) {
									c += ' marker-cluster-m';
									mWidth = 50;
								} else if (number < 1000000) {
									c += ' marker-cluster-l';
									mWidth = 60;
								} else {
									c += ' marker-cluster-xl';
									mWidth = 65;
								}
								return new L.DivIcon({ html: '<div><span>' + number + '</span></div>', className: 'marker-cluster' + c, iconSize: new L.Point(mWidth, 40) });
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
								var marker = new L.Marker([parseFloat(clusterData[0]), parseFloat(clusterData[1])], {displayNumber: parseInt(clusterData[2])});
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
								if (number < 8)
									c += 'small';
								else if (number < 35)
									c += 'medium';
								else
									c += 'large';
								
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
									if(clusterData[7] != '')
									{
										var polyLayer = L.geoJson(JSON.parse(clusterData[7])).bindPopup("<center><b>" +  clusterData[1] + 
														"</b></center><br>LAC: " + clusterData[0] + 
														"<br>MNC: " + clusterData[2] + 
														"<br>MCC: " + clusterData[3]);
									}
									
									var marker = new L.Marker([parseFloat(clusterData[6]), parseFloat(clusterData[5])], 
																	{displayNumber: 1, lacPoly: polyLayer, lac: clusterData[0],
																	mnc: clusterData[2], mcc: clusterData[3], radio: clusterData[1]})
												.bindPopup("<center><b>LAC: " + clusterData[0] + "</b></br>Size: " + clusterData[4] + "</center>")
												.on('click', function(e) {
													if(map.hasLayer(cvLACOutlineLayer))
														map.removeLayer(cvLACOutlineLayer);
													
													if(this.options.lac != selectedLac)
													{
														cvLACOutlineLayer = this.options.lacPoly;
														map.addLayer(cvLACOutlineLayer);
														selectedLac = this.options.lac;
													}else
														selectedLac = undefined;
													})
												.on('mouseover', function (e) {
													cvLACPolyHoverLayer.clearLayers();
													cvLACPolyHoverLayer.addLayer(this.options.lacPoly);
													this.openPopup();
													})
												.on('mouseout', function (e) {
													cvLACPolyHoverLayer.clearLayers();
													this.closePopup();
													})
												.on('click', function (e) {
													cvLACPolyHoverLayer.clearLayers();
													if(map.hasLayer(cvLACOutlineLayer))
														map.removeLayer(cvLACOutlineLayer);
													cvLACOutlineLayer = this.options.lacPoly;
													map.addLayer(cvLACOutlineLayer);
													})
												.on('dblclick', function(e) {
													cvLACPolyHoverLayer.clearLayers();
													if(map.hasLayer(cvLACOutlineLayer))
														map.removeLayer(cvLACOutlineLayer);
													cvLACOutlineLayer = this.options.lacPoly;
													map.addLayer(cvLACOutlineLayer);
													selectedLac = this.options.lac;
													
													// Zoom to LAC (-> LAC Search)
													$("#sMcc").val(this.options.mcc);
													$("#sMnc").val(this.options.mnc);
													$("#sLac").val(this.options.lac);
													$("#sRadio").val(this.options.radio);
													$("#sId").val("");
													loadLacData(this.options.mcc, this.options.mnc, this.options.lac, this.options.radio);
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
						cellViewLayer.addLayer(heatLayer);
						
					}else notify("Error in Response: " + data);
					
					if(typeof mlsMarkerCluster !== 'undefined')
						cellViewLayer.addLayer(mlsMarkerCluster);
					
					map.addLayer(cellViewLayer);
					
					$("#loadingGif").hide();
				}
			});
		}
	});
}

function setParams()
{
	// Save new Params
	if($("#SETignoreOldData").is(':checked'))
		paraIgnoreOldData = true;
	else
		paraIgnoreOldData = false;

	var ageStr = $("#SEToldDataThreshold").val();
	paraIgnoreDataAge = parseInt(parseInt(ageStr)) * 2628000;
	
	paraDataSource = $("#SETdataSource").val();
	
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

function init() // All static one-time stuff is here
{
	// Init Map
	osmLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
		attribution: 'Map data &copy; <a href="https://openstreetmap.org">OpenStreetMap</a> contributors, ' + 
			' <a href="https://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>',
		maxZoom: 18,
		minZoom: 2,
	});
	
	otmLayer = L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', {
		attribution: 'Map data &copy; <a href="https://openstreetmap.org">OpenStreetMap</a> contributors, Tiles:  &copy; <a href="https://opentopomap.org">OpenTopoMap</a>' + 
			' <a href="https://creativecommons.org/licenses/by-sa/3.0/">CC-BY-SA</a>',
		maxZoom: 18,
		minZoom: 2,
	});
	
	map = L.map('map', {
		center: [49.574, 11.0294],
		zoom: 14,
		layers: [osmLayer]
	});
	
	var baseMaps = {
		"OpenStreetMap": osmLayer,
		"OpenTopoMap": otmLayer
	};
	
	map.removeLayer(otmLayer);
	
	L.control.layers(baseMaps).addTo(map);
	map.addLayer(cvLACPolyHoverLayer);
		
	new L.Control.GeoSearch({
		provider: new L.GeoSearch.Provider.OpenStreetMap(),
		position: 'topleft',
	}).addTo(map);
	
	L.control.scale({position: 'topleft', maxWidth: 150}).addTo(map);
		
	// Load Settings
	loadFromCookie();
	
	// Load Builddate
	$.post('getInfo.php', {para: 'DB_DATE_STRING'}, function(data){
		$("#buildInfo").append(data);
	});
	
	// Ajax Setup
	$.ajaxSetup({
		timeout: paraAJAXTimeout,
		error: function(x, t, m) {
			if(t==="timeout")
				notify("Server took to long to respond. May be overloaded?");
			waitingForHash = 0;
			$("#loadingGif").hide();
		}
	});
	
	// Lots of UI Setup
	$("#settingsContainer").accordion({
      heightStyle: "content"
    });
	
	$(document).tooltip({
		tooltipClass: "tooltipClass"
	});
	
	$("#cvModeDiv :input").checkboxradio({
		icon: false
    });
	$("#typeSelectDiv :input").checkboxradio({
		icon: false
    });
	$("#mncSelectDiv :input").checkboxradio({
		icon: false
    });
	
	$("#settingsBtn").button({
		icons: {secondary: "ui-icon-newwin"}
	});
	
	$("#sBtn").button({
		icons: {secondary: "ui-icon-newwin"}
	});
	
	$("#notificationDiv").hide();
	$("#mncDisabledText").hide();
	$("#informationText").hide();
	$("#loadingGif").hide();
	$("#searchDiv").hide();

	$("#searchDialog").dialog({
		autoOpen: false,
		width: 218,
		maxWidth: 218,
		buttons: 	[{
						text: "Search",
						click: function() {
							search();
						}
					},
					{
						text: "Close",
						click: function() {
							$("#searchDialog").dialog("close");
						}
					}],
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
	
	$("#searchDialog").keypress(function(e) {
		if (e.keyCode == $.ui.keyCode.ENTER) {
			search();
		}
	});
		
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
							$(this).dialog( "close" );
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
							window.location.href = window.location.href.substring(0, window.location.href.indexOf("?"));
						},
					}
				],
				open: function(){
					// Load UI
					$("#SETignoreOldData").button();
					$("#SEToldDataThreshold").addClass("ui-widget ui-widget-content ui-corner-all");
					$("#SETajaxTimeout").selectmenu();
					$("#SETdataSource").selectmenu();
					$("#SETsearchClusterRadius").addClass("ui-widget ui-widget-content ui-corner-all");
					$("#SETsearchClusterDisableLevel").selectmenu();
					
					$("#SETcellClusterDisableLevel").selectmenu();
					$("#SETcellMaxCellAmount").addClass("ui-widget ui-widget-content ui-corner-all");
					$("#SETcellClusterRadius").addClass("ui-widget ui-widget-content ui-corner-all");
					$("#SETclusteredClusterRadius").addClass("ui-widget ui-widget-content ui-corner-all");
					
					$("#SETfilterLACs").button();
					$("#SETlacFilterLimit").addClass("ui-widget ui-widget-content ui-corner-all");
					
					$("#SETLACClusterRadius").addClass("ui-widget ui-widget-content ui-corner-all");
					$("#SETLACMaxLacAmount").addClass("ui-widget ui-widget-content ui-corner-all");
					
					$("#SETparaLACClusterRadius").addClass("ui-widget ui-widget-content ui-corner-all");
					$("#SETLACClusteredClusterRadius").addClass("ui-widget ui-widget-content ui-corner-all");
					$("#SETLACClusterDisableLevel").selectmenu();
					$("#SETLACMaxCLacAmount").addClass("ui-widget ui-widget-content ui-corner-all");
					
					$("#SETHMClusteredMaxDivider").addClass("ui-widget ui-widget-content ui-corner-all");
					$("#SETHMDynamicCompareModifier").addClass("ui-widget ui-widget-content ui-corner-all");
					$("#SETHMDynamicValueModifier").selectmenu();
					
					$("#SETHMBlur").addClass("ui-widget ui-widget-content ui-corner-all");
					$("#SETHMRadius").addClass("ui-widget ui-widget-content ui-corner-all");
					
					// Populate UI
					$("#SETignoreOldData").attr("checked", paraIgnoreOldData).button("refresh");
					var months = Math.floor(paraIgnoreDataAge / 2628000);
					$("#SEToldDataThreshold").val(months);
					
					$("#SETajaxTimeout").val(paraAJAXTimeout).selectmenu("refresh");
					$("#SETdataSource").val(paraDataSource).selectmenu("refresh");
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

	// Restore from URL Parameters
    var uIndex = window.location.href.indexOf("?");
    if (uIndex != -1)
	{
		var resArray = {};
		var pString = decodeURIComponent(window.location.href.substring(uIndex + 1));
		var pArray = pString.split("&");
		
		for (var i = 0; i < pArray.length; i++)
		{
			var uPar = pArray[i].split("=");
			resArray[uPar[0]] = uPar[1];
		}
		
		switch(resArray["m"]){
			case "s":	// search Mode. Search for LAC or Cell dependend on if i is set
				$("#sMcc").val(resArray["c"]);
				$("#sMnc").val(resArray["n"]);
				$("#sLac").val(resArray["a"]);
				$("#sRadio").val(resArray["r"]);
				if(resArray["i"] != undefined)
					$("#sId").val(resArray["i"]);
				search();
				break;
			case "m":	// Meas Mode. Create Marker and call getMeasData()
				$("#sMcc").val(resArray["c"]);
				$("#sMnc").val(resArray["n"]);
				$("#sLac").val(resArray["a"]);
				$("#sRadio").val(resArray["r"]);
				$("#sId").val(resArray["i"]);
				
				$.post( 'searchCells.php', {type: 'cell', mcc: $("#sMcc").val(), mnc: $("#sMnc").val()
							   , lac: $("#sLac").val(), cid: $("#sId").val(), radio: $("#sRadio").val(), dataSource: paraDataSource}, function( data )
				{
					if(data == "MULTIPLE")
					{
						notify("Multiple Cells Found.");
						return;
					} else if(data == "NONE")
					{
						notify("No Cell Found.");
						return;
					}
						
					var lonlatP = data.split("|");
					
					if(lonlatP.length < 3)
					{
						notify("Invalid Data Received. Database Error?");
						return;
					}
					
					loadMeasData(getCellMarker(parseFloat(lonlatP[1]), parseFloat(lonlatP[0]), $("#sMcc").val(), $("#sMnc").val(), $("#sLac").val(), $("#sId").val(), $("#sRadio").val(), lonlatP[2], lonlatP[3]));
				});
				break;
			case "norm":	// Cell view Modes. Set Map Location, Radio and Network settings and call loadCellData()
			case "lacSort":
			case "heat":
				if(resArray["m"] == "lacSort")
					$("#cvGLac").prop('checked', true);
				if(resArray["m"] == "heat")
					$("#cvHMMode").prop('checked', true);
				$("#cvModeDiv :input").checkboxradio("refresh");
				
				map.setView([parseFloat(resArray["la"]), parseFloat(resArray["lo"])], parseInt(resArray["z"]));
				
				if(resArray["r"].indexOf("GSM") == -1)
					$("#GSMBox").prop('checked', false);
				if(resArray["r"].indexOf("UMTS") == -1)
					$("#UMTSBox").prop('checked', false);
				if(resArray["r"].indexOf("LTE") == -1)
					$("#LTEBox").prop('checked', false);
				$("#typeSelectDiv :input").checkboxradio("refresh");
				
				if(resArray["n"] != "ALL")
					$("#mncAll").prop('checked', false);
				
				var mncs = resArray["n"].split("|")
				for (var i = 0; i < (mncs.length - 1); i++)
				{
					if(typeof lastObject !== 'undefined')
						lastObject.after("<input type='checkbox' id='mnc" + mncs[i] + 
											"' class='mncSelect'/><label id='mncLa" + mncs[i] + "' for='mnc" + mncs[i] + "'>" + mncs[i] + "</label>");	
					else
						$("#mncLaAll").after("<input type='checkbox' id='mnc" + mncs[i] + 
											"' class='mncSelect'/><label id='mncLa" + mncs[i] + "' for='mnc" + mncs[i] + "'>" + mncs[i] + "</label>");
					lastObject = $("#mncLa" + mncs[i]);
					$("#mnc" + mncs[i]).prop('checked', true);
				}
				$("#mncSelectDiv :input").checkboxradio({
					icon: false
				}).checkboxradio("refresh");
				
				loadCellData();
				break;
			default:
		}
	} else
		loadCellData();
}

$(document).ready(function()
{
	init();
	
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
		if($(this).attr("id") != "mncAll")
			$("#mncAll").prop('checked', false);

		if(autoLoad)
			loadCellData();
	});
	
	$('input:radio[name="cvModeS"]').change(function(){
		autoLoad = true;
		$("#mncDiv").show("fast");
		loadCellData();
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
			if(map.hasLayer(sLACPolyLayer))
				map.addLayer(sLACCellLayer);
		}else
		{
			if(map.hasLayer(sLACCellLayer))
				map.removeLayer(sLACCellLayer);
		}
	});
});
