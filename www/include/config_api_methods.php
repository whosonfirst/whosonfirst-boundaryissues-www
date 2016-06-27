<?php

	########################################################################

	$GLOBALS['cfg']['api']['methods'] = array_merge(array(

		"wof.upload_feature" => array (
			"description" => "Upload a file to create a WOF record.",
			"documented" => 1,
			"enabled" => 1,
			"library" => "api_wof",
			"requires_crumb" => 1,
			"request_method" => "POST",
			"parameters" => array(
				array("name" => "upload_file", "description" => "A GeoJSON file, multipart encoded", "required" => 1)
			)
		),

		"wof.upload_collection" => array (
			"description" => "Upload a collection of WOF records.",
			"documented" => 1,
			"enabled" => 1,
			"library" => "api_wof",
			"requires_crumb" => 1,
			"request_method" => "POST",
			"parameters" => array(
				array("name" => "upload_file", "description" => "A GeoJSON file, multipart encoded", "required" => 1)
			)
		),

		"wof.save" => array (
			"description" => "Save a WOF record.",
			"documented" => 1,
			"enabled" => 1,
			"library" => "api_wof",
			"requires_crumb" => 1,
			"request_method" => "POST",
			"parameters" => array(
				array("name" => "geojson", "description" => "A GeoJSON string.", "required" => 1)
			)
		),

		"wof.save_batch" => array (
			"description" => "Save multiple WOF records at once.",
			"documented" => 1,
			"enabled" => 1,
			"library" => "api_wof",
			"requires_crumb" => 1,
			"request_method" => "POST",
			"parameters" => array(
				array("name" => "ids", "description" => "Comma-separated list of WOF IDs to update.", "required" => 1),
				array("name" => "properties", "description" => "JSON hash of properties to update.", "required" => 1)
			)
		),

		"wof.pip" => array (
			"description" => "Point-in-polygon service.",
			"documented" => 1,
			"enabled" => 1,
			"library" => "api_wof",
			"requires_crumb" => 0,
			"request_method" => "POST",
			"parameters" => array(
				array("name" => "latitude", "description" => "Coordinate latitude", "required" => 1),
				array("name" => "longitude", "description" => "Coordinate longitude", "required" => 1)
			)
		),

		"wof.encode" => array (
			"description" => "Encode as GeoJSON string.",
			"documented" => 1,
			"enabled" => 1,
			"library" => "api_wof",
			"requires_crumb" => 0,
			"request_method" => "POST",
			"parameters" => array(
				array("name" => "geojson", "description" => "A GeoJSON string.", "required" => 1)
			)
		),

		"wof.search" => array (
			"description" => "Search for WOF records.",
			"documented" => 1,
			"enabled" => 1,
			"library" => "api_wof",
			"requires_crumb" => 0,
			"request_method" => "POST",
			"parameters" => array(
				array("name" => "query", "description" => "The search query.", "required" => 1)
			)
		),

		"wof.checkout_branch" => array (
			"description" => "Check out a branch in git.",
			"documented" => 1,
			"enabled" => 1,
			"library" => "api_wof",
			"requires_crumb" => 0,
			"request_method" => "POST",
			"parameters" => array(
				array("name" => "branch", "description" => "The branch name.", "required" => 1)
			)
		),

		"api.spec.methods" => array (
			"description" => "Return the list of available API response methods.",
			"documented" => 1,
			"enabled" => 1,
			"library" => "api_spec"
		),

		"api.spec.formats" => array(
			"description" => "Return the list of valid API response formats, including the default format",
			"documented" => 1,
			"enabled" => 1,
			"library" => "api_spec"
		),

		"test.echo" => array(
			"description" => "A testing method which echo's all parameters back in the response.",
			"documented" => 1,
			"enabled" => 1,
			"library" => "api_test"
		),

		"test.error" => array(
			"description" => "Return a test error from the API",
			"documented" => 1,
			"enabled" => 1,
			"library" => "api_test"
		),

	), $GLOBALS['cfg']['api']['methods']);

	########################################################################

	# the end
