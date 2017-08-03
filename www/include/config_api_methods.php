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
				array("name" => "upload_file", "description" => "A GeoJSON file, multipart encoded", "documented" => 1, "required" => 1)
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
				array("name" => "upload_file", "description" => "A GeoJSON file, multipart encoded", "documented" => 1, "required" => 1)
			)
		),

		"wof.upload_csv" => array (
			"description" => "Upload a CSV file to create new WOF records.",
			"documented" => 1,
			"enabled" => 1,
			"library" => "api_wof",
			"requires_crumb" => 1,
			"request_method" => "POST",
			"parameters" => array(
				array("name" => "upload_file", "description" => "A CSV file, multipart encoded", "documented" => 1, "required" => 1),
				array("name" => "column_properties", "description" => "A comma-separated list of WOF properties (or empty, to ignore the column), one assigned to each CSV column.", "documented" => 1, "required" => 1),
				array("name" => "row_count", "description" => "The number of rows in the CSV file.", "documented" => 1, "required" => 1)
			)
		),

		"wof.update_csv" => array (
			"description" => "Update CSV import settings.",
			"documented" => 1,
			"enabled" => 1,
			"library" => "api_wof",
			"requires_crumb" => 1,
			"request_method" => "POST",
			"parameters" => array(
				array("name" => "csv_id", "description" => "Which CSV settings to update", "documented" => 1, "required" => 1),
				array("name" => "wof_id", "description" => "The WOF ID to assign to a CSV row (combine with 'csv_row').", "documented" => 1, "required" => 0),
				array("name" => "csv_row", "description" => "Which CSV row to update the WOF ID for (combine with 'wof_id').", "documented" => 1, "required" => 0),
			)
		),

		"wof.upload_zip" => array (
			"description" => "Upload a zip file for pipeline processing.",
			"documented" => 1,
			"enabled" => 1,
			"library" => "api_wof",
			"requires_crumb" => 1,
			"request_method" => "POST",
			"parameters" => array(
				array("name" => "upload_file", "description" => "A zip file, multipart encoded", "documented" => 1, "required" => 1),
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
				array("name" => "geojson", "description" => "A GeoJSON string.", "documented" => 1, "required" => 1)
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
				array("name" => "ids", "description" => "Comma-separated list of WOF IDs to update.", "documented" => 1, "required" => 1),
				array("name" => "properties", "description" => "JSON hash of properties to update.", "documented" => 1, "required" => 1)
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
				array("name" => "latitude", "description" => "", "documented" => 1, "required" => 1),
				array("name" => "longitude", "description" => "", "documented" => 1, "required" => 1),
				array("name" => "placetype", "description" => "", "documented" => 1, "required" => 1)
			),
		),

		"wof.encode" => array (
			"description" => "Encode as GeoJSON string.",
			"documented" => 1,
			"enabled" => 1,
			"library" => "api_wof",
			"requires_crumb" => 0,
			"request_method" => "POST",
			"parameters" => array(
				array("name" => "geojson", "description" => "A GeoJSON string.", "documented" => 1, "required" => 1)
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
				array("name" => "query", "description" => "The search query.", "documented" => 1, "required" => 1)
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
				array("name" => "branch", "description" => "The branch name.", "documented" => 1, "required" => 1)
			)
		),

		"wof.users_settings_set" => array (
			"description" => "Set a users setting.",
			"documented" => 1,
			"enabled" => 1,
			"library" => "api_wof",
			"requires_crumb" => 0,
			"request_method" => "POST",
			"parameters" => array(
				array("name" => "name", "description" => "The setting name.", "documented" => 1, "required" => 1),
				array("name" => "value", "description" => "The setting value.", "documented" => 1, "required" => 1)
			)
		),

		"wof.photos_get" => array (
			"description" => "Finds photos for a WOF record.",
			"documented" => 1,
			"enabled" => 1,
			"library" => "api_wof",
			"requires_crumb" => 0,
			"request_method" => "POST",
			"parameters" => array(
				array("name" => "wof_id", "description" => "The WOF ID.", "documented" => 1, "required" => 1)
			)
		),

		"wof.assign_flickr_photo" => array (
			"description" => "Assigns a photo to a WOF record.",
			"documented" => 1,
			"enabled" => 1,
			"library" => "api_wof",
			"requires_crumb" => 0,
			"request_method" => "POST",
			"parameters" => array(
				array("name" => "wof_id", "description" => "The WOF ID.", "documented" => 1, "required" => 1),
				array("name" => "flickr_id", "description" => "The Flickr photo ID.", "documented" => 1, "required" => 1)
			)
		),

		"wof.places.get_nearby" => array(
			"description" => "",
			"documented" => 1,
			"enabled" => 1,
			"paginated" => 0,
			"library" => "api_wof_places",
			"parameters" => array(
				array("name" => "latitude", "description" => "", "documented" => 1, "required" => 1),
				array("name" => "longitude", "description" => "", "documented" => 1, "required" => 1),
				array("name" => "cursor", "description" => "", "documented" => 1, "required" => 0),
				# array("name" => "extras", "description" => "comma-separated list of additional fields to include in results", "documented" => 1, "required" => 0),
			),
		),

		"wof.places.search" => array(
			"description" => "",
			"documented" => 1,
			"enabled" => 1,
			"paginated" => 0,
			"library" => "api_wof_places",
			"parameters" => array(
				# See https://mapzen.com/documentation/wof/methods/#whosonfirst.places.search
			),
		),

		"wof.pipeline.create" => array (
			"description" => "Create a new pipeline.",
			"documented" => 1,
			"enabled" => 1,
			"library" => "api_wof_pipeline",
			"requires_crumb" => 1,
			"request_method" => "POST",
			"parameters" => array(
				array("name" => "meta_json", "description" => "Metadata about the pipeline.", "documented" => 1, "required" => 1),
			)
		),


		"wof.pipeline.update" => array(
			"description" => "Update a pipeline's phase.",
			"documented" => 1,
			"enabled" => 1,
			"request_method" => "POST",
			"requires_crumb" => 1,
			"library" => "api_wof_pipeline",
			"parameters" => array(
				array("name" => "id", "description" => "The pipeline ID to update", "documented" => 1, "required" => 1),
				array("name" => "action", "description" => "Possible values: 'retry', 'cancel', 'confirm'", "documented" => 1, "required" => 1),
			),
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

	if ($GLOBALS['cfg']['enable_feature_libpostal']){
		$GLOBALS['cfg']['api']['methods'] = array_merge(array(
			"wof.address_lookup" => array(
				"description" => "Look up an address",
				"documented" => 1,
				"enabled" => 1,
				"paginated" => 0,
				"library" => "api_wof",
				"parameters" => array(
					array("name" => "query", "description" => "", "documented" => 1, "required" => 1),
				),
			)
		), $GLOBALS['cfg']['api']['methods']);
	}

	# the end
