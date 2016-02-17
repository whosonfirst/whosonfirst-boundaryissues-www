<?php
	loadlib('wof_utils');
	loadlib('artisanal_integers');

	function wof_venue_create() {

	}

	function wof_schema_fields($ref, $ignore_fields = null) {
		global $wof_schema_lookup;
		if (empty($wof_schema_lookup)) {
			$wof_schema_lookup = wof_load_schemas(array(
				'whosonfirst.schema',
				'geojson.schema',
				'geometry.schema',
				'bbox.schema'
			));
		}
		$schema_fields = wof_schema_filter($wof_schema_lookup[$ref], $ignore_fields);
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

	function wof_schema_filter($schema, $ignore_fields = null) {
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
