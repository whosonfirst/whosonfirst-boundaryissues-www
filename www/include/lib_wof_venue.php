<?php
	loadlib('wof_utils');
	loadlib('artisanal_integers');

	function wof_venue_create($geojson) {
		$geojson_data = json_decode($geojson, true);
		if (! $geojson_data){
			return array(
				'ok' => 0,
				'error_msg' => "Could not parse input; invalid JSON."
			);
		}

		// Mint a new artisanal integer wof:id
		$rsp = artisanal_integers_create();
		if (! $rsp['ok']){
			// Weird, this message doesn't seem to make it back to the AJAX requester
			$rsp['error_msg'] = 'Could not load artisanal integer.';
			return $rsp;
		}

		// Write WOF ID to the top-level 'id' and 'properties/wof:id'
		$geojson_data['id'] = intval($rsp['integer']);
		$geojson_data['properties']['wof:id'] = intval($rsp['integer']);

		// Send GeoJSON to Python script to get prettied up
		$rsp = http_post('http://localhost:5000/geojson-encode', array(
			'geojson' => json_encode($geojson_data)
		));
		if (! $rsp['ok']) {
			$rsp['error_msg'] = 'Error encoding GeoJSON.';
			return $rsp;
		}
		$geojson = $rsp['body'];

		// Figure out where we're going to put the incoming file
		$geojson_path = wof_utils_id2abspath(
			$GLOBALS['cfg']['wof_venue_dir'],
			$geojson_data['properties']['wof:id']
		);
		$geojson_dir = dirname($geojson_path);

		// Create the directory structure, if it doesn't exist
		if (! file_exists($geojson_dir)){
			mkdir($geojson_dir, 0775, true);
		}

		// Write the file
		file_put_contents($geojson_path, $geojson);

		$geojson_url = '/venue/' . wof_utils_id2relpath($geojson_data['id']);

		// It worked \o/
		return array(
			'ok' => 1,
			'id' => $geojson_data['id'],
			'geojson_url' => $geojson_url
		);
	}

	function wof_schema_fields($ref, $ignore_fields = null, $values = null) {
		global $wof_schema_lookup;
		if (empty($wof_schema_lookup)) {
			$wof_schema_lookup = wof_load_schemas(array(
				'whosonfirst.schema',
				'geojson.schema',
				'geometry.schema',
				'bbox.schema'
			));
		}
		$schema_fields = wof_schema_filter($wof_schema_lookup[$ref], $ignore_fields, $values);
		return $schema_fields;
	}

	function wof_load_schemas($schema_files) {
		$schemas = array();
		foreach ($schema_files as $filename) {
			$path = realpath(FLAMEWORK_INCLUDE_DIR . "../../schema/json/$filename");
			$json = file_get_contents($path);
			$schema = json_decode($json, true);
			$schema_id = $schema['id'];
			$schemas[$schema_id] = $schema;
		}
		return $schemas;
	}

	function wof_schema_filter($schema, $ignore_fields = null, $values = null) {
		if ($schema['allOf']) {
			foreach ($schema['allOf'] as $part_of) {
				if ($part_of['$ref']) {
					$ref_fields = wof_schema_fields($part_of['$ref'], $ignore_fields);
					$schema = array_merge_recursive($schema, $ref_fields);
				} else {
					$schema = array_merge_recursive($schema, $part_of);
				}
			}
		}
		if ($ignore_fields) {
			// We don't always want to show all the fields all the time
			$schema = wof_schema_remove_ignored($schema, $ignore_fields);
		}
		if ($values) {
			// Insert values into the field definitions
			$schema = wof_schema_insert_values($schema, $values);
		}
		return $schema;
	}

	function wof_schema_remove_ignored($schema, $ignore_fields) {
		foreach ($ignore_fields as $key => $field) {
			if (is_scalar($field)) {
				unset($schema['properties'][$field]);
			} else if ($schema['properties'][$key]) {
				$schema['properties'][$key] = wof_schema_remove_ignored($schema['properties'][$key], $field);
			}
		}
		return $schema;
	}

	function wof_schema_insert_values($schema, $values) {
		if (is_array($values)) {
			foreach ($values as $key => $value) {
				if (is_scalar($value)) {
					$schema['properties'][$key]['value'] = $value;
				} else if ($schema['properties'][$key]) {
					$schema['properties'][$key] = wof_schema_insert_values($schema['properties'][$key], $value);
				}
			}
		}
		return $schema;
	}
