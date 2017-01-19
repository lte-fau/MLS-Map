# MLS-Map

A map visualization of the Mozilla Location Service and OpenCellID databases.

## Usage

This project requires a PostGIS enabled PostgreSQL Database.
Login information and table names are stored in _db-settings.php_. All other configuration is stored in _config.php_. The contents of config.php are copied to a database, therefore changes won't be applied after first generation.

##### 1.: Two functions are needed to enable the dbBuilder to work:

The first works around _ST_Concavehull_ sometimes failing. It allows _null_ to be returned.

```sql
CREATE OR REPLACE FUNCTION public.st_nullableconcavehull(param_geom geometry, param_pctconvex double precision, param_allow_holes boolean DEFAULT false)
	RETURNS geometry
	AS $BODY$
	DECLARE
	BEGIN       
		RETURN public.st_concavehull(param_geom,param_pctconvex,param_allow_holes);
		EXCEPTION when others then RETURN null;
	END;
$BODY$
	LANGUAGE plpgsql IMMUTABLE STRICT
	COST 100;
ALTER FUNCTION public.st_nullableconcavehull(geometry, double precision, boolean)
	OWNER TO postgres;
COMMENT ON FUNCTION public.st_nullableconcavehull(geometry, double precision, boolean) IS 'args: geomA, target_percent, allow_holes=false - The concave hull of a geometry represents a possibly concave geometry that encloses all geometries within the set. You can think of it as shrink wrapping.';
```


The second enables usage of the _COPY_ command to allow fast import of the .csv data files. It has to be created by a superuser.

```sql
CREATE FUNCTION import_csv_file_to_table(table_name text, file_name text)
	RETURNS VOID
	LANGUAGE plpgsql
	-- source: http://rwec.co.uk/blog/2014/02/securely-importing-and-exporting-csv-with-postgresql/
	-- The magic ingredient: Anyone who can execute this can do so with superuser privileges,
	--	as long as the function was created while logged in as a superuser.
	SECURITY DEFINER
	AS $BODY$
	DECLARE
		-- These must be as restrictive as possible, for security reasons
		-- Hard-coded directory in which all CSV files to import will be placed
		file_path text := '***PATH_TO_ADMIN/TMP_FOLDER***';
		-- File names must contain only alphanumerics, dashes and underscores,
		--	and all must end in the extension .csv
		file_name_regex text := E'^[a-zA-Z0-9_-]+\\.csv$';
	BEGIN
		-- Sanity check input
		IF file_name !~ file_name_regex
		THEN
			RAISE EXCEPTION 'Invalid data file name (% doesn''t match %)', file_name, file_name_regex;
		END IF;
		-- OK? Go!
		-- Make sure there's zero chance of SQL injection here
		EXECUTE '
			COPY ' || quote_ident(table_name) || '
			FROM ' || quote_literal(file_path || file_name) || '
			WITH (FORMAT CSV, HEADER);
		';
	END;
$BODY$;
-- Don't let just anyone do this privileged thing
REVOKE ALL ON FUNCTION import_csv_file_to_table( table_name text, file_name text )
	FROM PUBLIC;
GRANT EXECUTE ON FUNCTION import_csv_file_to_table( table_name text, file_name text )
	TO ***YOUR_DB_USER***;
```

##### 2.: Make sure php can write to _admin/tmp/_

##### 3.: Login into the Admin-Interface and create the databases.
For Mozilla Location Service, a download link to a full cell export is required.

For OpenCellID, the API-Key from  _db-settings.php_ is used to download the newest export.

##### 3.: Postgres should be configured to optimize performance.
The following is an example, optimal settings will most likely be different.
Code must be run as superuser.

```sql
ALTER SYSTEM SET random_page_cost = 1.5; 			-- Default 4
ALTER SYSTEM SET shared_buffers = "3GB"; 			-- Default 128MB
ALTER SYSTEM SET effective_cache_size = "5GB";		-- Default 4GB

ALTER SYSTEM SET work_mem = "24MB";					-- Default 4MB
ALTER SYSTEM SET maintenance_work_mem = "1024MB";	-- Default 64MB

ALTER SYSTEM SET max_wal_size = "4GB";				-- Default 1GB
ALTER SYSTEM SET checkpoint_completion_target = 0.9;-- Default 0.5
```

## Third-party modules

Leaflet 1.0.2
https://github.com/Leaflet/Leaflet/

Leaflet Markercluster leaflet 1.0.0 (Modified)
https://github.com/Leaflet/Leaflet.markercluster/

Leaflet Heat 0.2.0 
https://github.com/Leaflet/Leaflet.heat/

Leaflet geosearch 1.1.0
https://github.com/smeijer/L.GeoSearch/

Jquery 3.1.1
https://github.com/jquery/jquery/

Jquery UI 1.12.1
https://github.com/jquery/jquery-ui/

js.cookie 2.1.3
https://github.com/js-cookie/js-cookie/