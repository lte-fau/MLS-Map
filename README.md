# MLS-Map

A map visualization of the Mozilla Location Service database.

## Usage

This project requires a PostGIS enabled PostgreSQL Database.
Login information is stored in the _db-settings.php_.

1.: Run the _ccHullWorkaround.php_ script. _ST_Concavehull_ sometimes fails. To prevent the tablecreation from failing, this script allows _null_ to be returned.

2.: Get a full-cell-export of the Mozilla Location Service Database and copy the _.csv_ file to your server.

3.: Change the filepath in the _dbBuilder.php_ script to your cell-export .csv.

4.: Run the _dbBuilder.php_ script. This may take a long time (hours).

## Third-party modules

Leaflet 0.7.7
https://github.com/Leaflet/Leaflet/

Leaflet Markercluster leaflet 0.7 (Modified)
https://github.com/Leaflet/Leaflet.markercluster/

Leaflet Heat 0.2.0 
https://github.com/Leaflet/Leaflet.heat/

Leaflet geosearch 1.1.0
https://github.com/smeijer/L.GeoSearch/

Jquery 2.2.3
https://github.com/jquery/jquery/

Jquery UI 1.11.4
https://github.com/jquery/jquery-ui/

js.cookie 2.1.1
https://github.com/js-cookie/js-cookie/