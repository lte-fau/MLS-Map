<?php
/* Copyright (C) 2016  Lehrstuhl für Technische Elektronik, Friedrich-Alexander-Universität Erlangen-Nürnberg */
/* https://github.com/lte-fau/MLS-Map/blob/master/LICENSE */

	// PostGis Concavehull sometimes fails with a float value under 0.99. This workaround allows null to be returned
	
	// Create connection
	include $_SERVER['DOCUMENT_ROOT'] . "/db/db-settings.php";
	$conn = pg_connect($connString)
		or die('Could not connect: ' . pg_last_error());

	echo "Connected successfully\n";
	
	$sql = "DROP FUNCTION IF EXISTS public.st_nullableconcavehull(geometry, double precision, boolean)"
	pg_query($conn, $sql);
	
	
	$sql = "CREATE OR REPLACE FUNCTION public.st_nullableconcavehull(param_geom geometry, param_pctconvex double precision, param_allow_holes boolean DEFAULT false)
				RETURNS geometry AS
			$BODY$
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
			COMMENT ON FUNCTION public.st_nullableconcavehull(geometry, double precision, boolean) IS 'args: geomA, target_percent, allow_holes=false - The concave hull of a geometry represents a possibly concave geometry that encloses all geometries within the set. You can think of it as shrink wrapping.';"
	pg_query($conn, $sql);

	pg_close($conn);
?>
