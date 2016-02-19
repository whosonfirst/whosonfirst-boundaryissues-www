<?php

	########################################################################

	$GLOBALS['cfg']['api']['methods'] = array_merge(array(

		"wof.upload" => array (
			"description" => "Upload a GeoJSON file.",
			"documented" => 1,
			"enabled" => 1,
			"library" => "api_wof",
			"requires_crumb" => 1,
			"request_method" => "POST",
			"parameters" => array(
				array("name" => "upload_file", "description" => "A GeoJSON file, multipart encoded", "required" => 1)
			)
		),
		
		"wof.pip" => array (
			"description" => ".",
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

		"wof.venue.create" => array (
			"description" => "Create a new venue.",
			"documented" => 1,
			"enabled" => 1,
			"library" => "api_wof_venue",
			"requires_crumb" => 1,
			"request_method" => "POST",
			"parameters" => array(
				array("name" => "venue", "description" => "A GeoJSON string", "required" => 1)
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
