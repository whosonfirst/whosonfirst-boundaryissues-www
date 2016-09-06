<?php

	########################################################################

	function api_whosonfirst_utils_ensure_bbox(){

		$sw_lat = request_float("sw_latitude");

		if (! $sw_lat){
			api_output_error(400, "Missing SW latitude");
		}

		if (! geo_utils_is_valid_latitude($sw_lat)){
			api_output_error(400, "Invalid SW latitude");
		}

		$sw_lon = request_float("sw_longitude");

		if (! $sw_lon){
			api_output_error(400, "Missing SW longitude");
		}

		if (! geo_utils_is_valid_longitude($sw_lon)){
			api_output_error(400, "Invalid SW longitude");
		}

		$ne_lat = request_float("ne_latitude");

		if (! $ne_lat){
			api_output_error(400, "Missing NE latitude");
		}

		if (! geo_utils_is_valid_latitude($ne_lat)){
			api_output_error(400, "Invalid NE latitude");
		}

		$ne_lon = request_float("ne_longitude");

		if (! $ne_lon){
			api_output_error(400, "Missing NE longitude");
		}

		if (! geo_utils_is_valid_longitude($ne_lon)){
			api_output_error(400, "Invalid NE longitude");
		}

		return array($sw_lat, $sw_lon, $ne_lat, $ne_lon);
	}

	########################################################################

	# the end
