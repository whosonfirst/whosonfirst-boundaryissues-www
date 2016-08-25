<?php

	/*

	This is a partial implementation of a JSON Schema parser. The part you
	probably want to know about is how wof_schema_fields($ref) goes and
	grabs a data structure from one of the files in the schema/json/ folder,
	according to that schema's 'id' property.

	So for example:

		$ref = 'https://whosonfirst.mapzen.com/schema/whosonfirst.schema#';
		$schema = wof_schema_fields($ref);

	.. will get you a complete schema for schema/json/whosonfirst.schema.
	That document includes details from other files, using the 'allOf'
	property. Basically any time you see a 'ref' it is a way of referencing
	another document by its 'id'.

	There is some more info about JSON schema (and the GeoJSON schema we
	modeled our stuff from) over here:
		http://json-schema.org/
		https://github.com/fge/sample-json-schemas/tree/master/geojson

	(20160412/dphiffer)

	*/

	########################################################################

	function wof_schema_fields($ref, $only_required = false) {
		global $wof_schema_lookup;
		if (empty($wof_schema_lookup)) {
			$wof_schema_lookup = wof_schema_load(array(
				'whosonfirst.schema',
				'geojson.schema',
				'geometry.schema',
				'bbox.schema'
			));
		}
		$schema_fields = wof_schema_filter($wof_schema_lookup[$ref], $only_required);
		return $schema_fields;
	}

	########################################################################

	function wof_schema_load($schema_files) {
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

	########################################################################

	function wof_schema_filter($schema, $only_required = false) {

		// Include 'allOf' sub-schemas into schema
		if ($schema['allOf']) {
			foreach ($schema['allOf'] as $part_of) {
				if ($part_of['$ref']) {
					$ref_fields = wof_schema_fields($part_of['$ref']);
					$schema = array_merge_recursive($schema, $ref_fields);
				} else {
					$schema = array_merge_recursive($schema, $part_of);
				}
			}
			unset($schema['allOf']);
		}

		// Mark properties that are required
		$props = $schema['properties']['properties']['properties'];
		$required = $schema['properties']['properties']['required'];
		if ($props && $required) {
			foreach ($props as $name => $prop) {
				if (in_array($name, $required)) {
					$schema['properties']['properties']['properties'][$name]['_required'] = true;
				}
			}
		}

		return $schema;
	}

	########################################################################

	# the end
