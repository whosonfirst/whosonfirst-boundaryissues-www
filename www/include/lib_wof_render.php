<?php

	/*

	So what is this all about?

	Let's start with what has happened already, before this part comes in.
	A JSON Schema has been used to generate a Minimum Viable WOF document:

		$schema_fields = wof_schema_fields($ref);

	See also: schema/json/whosonfirst.schema

	Next, values from a GeoJSON record get merged into that schema structure
	using an invented (i.e., not-part-of-JSON-schema) hash key "_value".

		$schema_fields = wof_render_insert_values($schema_fields, $values);

	That schema gets handed off to a Smarty template inc_json_schema_field.txt
	which decides what kind of thing it's dealing with. In the simplest case
	it comes up with a normal <input> field. Otherwise it includes one of
	inc_json_schema_object.txt or inc_json_schema_array.txt (which in turn
	include inc_json_schema_field.txt).

	In the case of inc_json_schema_object.txt, a <table> with <tr> elements
	gets generated, each one with possible 'class' attributes defined:

	- property-editable
	- property-visible

	By default a property <tr> is hidden by CSS, but property-visible
	overrides that to make the property visible. Likewise, <input>'s include
	a `disabled="disabled"` attribute, which gets overridden in JS if the
	property includes property-editable.

	This is all to say, you can modify the edit behavior for each property
	by adjusting the data structure below. The '_default' case is what it
	sounds like.

	(20160412/dphiffer)

	*/

	$GLOBALS['wof_property_classes'] = array(
		'_default' => array(
			'visible' => true,
			'editable' => true,
			'deletable' => true,
			'minimum_viable' => false
		),
		'wof:id' => array(
			'editable' => false,
			'minimum_viable' => true
		),
		'wof:placetype' => array(
			'deletable' => false,
			'minimum_viable' => true
		),
		'wof:name' => array(
			'deletable' => false,
			'minimum_viable' => true
		),
		'wof:parent_id' => array(
			'editable' => false,
			'minimum_viable' => true
		),
		'wof:hierarchy' => array(
			'deletable' => false,
			'editable' => false
		),
		'wof:country' => array(
			'deletable' => false
		),
		'iso:country' => array(
			'deletable' => false
		),
		'src:geom' => array(
			'deletable' => false
		),
		'edtf:inception' => array(
			'deletable' => false
		),
		'edtf:cessation' => array(
			'deletable' => false
		),
		'wof:concordances' => array(
			'deletable' => false
		),
		'wof:belongsto' => array(
			'visible' => false
		),
		'wof:supersedes' => array(
			'deletable' => false
		),
		'wof:superseded_by' => array(
			'deletable' => false
		),
		'wof:breaches' => array(
			'visible' => false
		),
		'wof:tags' => array(
			'deletable' => false
		),
		'wof:geomhash' => array(
			'editable' => false
		),
		'wof:created' => array(
			'editable' => false
		),
		'wof:controlled' => array(
			'deletable' => false
		),
		'wof:lastmodified' => array(
			'editable' => false
		),
		'geom:hash' => array(
			'editable' => false
		),
		'geom:area' => array(
			'editable' => false
		),
		'geom:bbox' => array(
			'editable' => false
		),
		'geom:latitude' => array(
			'deletable' => false
		),
		'geom:longitude' => array(
			'deletable' => false
		),
		'sg:classifiers' => array(
			'editable' => false
		),
		'mz:categories' => array(
			'editable' => false
		)
	);

	// See also: https://github.com/whosonfirst/whosonfirst-names/blob/master/inventory/inventory.py#L12-L31
	$GLOBALS['names_default_languages'] = array(
		"ara",
		"zho",
		"eng",
		"spa",
		"fre",
		"rus",
		"por",	# proposed UN
		"ben",	# proposed UN
		"hin",	# proposed UN
		"tur",	# proposed UN
		"ger",	# because tilezen
		"jap",	# because tilezen
		"kor",	# because tilezen
		"ita",	# because tilezen
		"gre",	# because tilezen
		"vie"   # because tilezen
	);

	########################################################################

	function wof_render_insert_values(&$schema, $values) {
		if (! is_array($values)) {
			return $schema;
		}
		foreach ($values as $key => $value) {
			if (is_scalar($value)) {
				if (! $schema['properties'][$key]) {
					$schema['properties'][$key] = array();
				}
				$schema['properties'][$key]['_value'] = $value;
				if (! $schema['properties'][$key]['type']) {
					$type = 'string';
					if (is_int($value)) {
						$type = 'integer';
					} else if (is_float($value)) {
						$type = 'number';
					}
					$schema['properties'][$key]['type'] = $type;
				}
			} else if ($schema['properties'][$key]) {
				// If the field is in the schema, recurse!
				$schema['properties'][$key] = wof_render_insert_values(
					$schema['properties'][$key],
					$value
				);
			} else {
				// ... otherwise, JSON encode the value
				$schema['properties'][$key]['type'] = 'json';
				$schema['properties'][$key]['_value'] = json_encode($value);
			}
		}
		return $schema;
	}

	########################################################################

	function wof_render_prune(&$schema) {
		foreach ($schema['properties'] as $key => $value) {
			if (! in_array($key, $GLOBALS['cfg']['wof_default_properties']) &&
			    ! $value['_required'] &&
			    ! (isset($value['_value']) ||
			       ! empty($value['properties']))) {
				// If a property hasn't been set in the existing record, and
				// isn't required, then we don't need it. The reason the property
				// exists is so that we can specify datatypes in the JSON
				// schema. So basically this hides all the specified properties
				// that haven't actually been set in a given WOF record.
				// (20160826/dphiffer)
				unset($schema['properties'][$key]);
			}
		}
		return $schema;
	}

	########################################################################

	function wof_render_property_groups(&$properties){
		$groups = array();
		foreach ($properties['properties'] as $name => $prop){
			if (preg_match('/^([^:]+):(.+)$/', $name, $matches)){
				list($name, $namespace, $predicate) = $matches;
				if (! $groups[$namespace]){
					$groups[$namespace] = array($predicate);
				} else {
					$groups[$namespace][] = $predicate;
				}
			}
		}
		return $groups;
	}

	########################################################################

	function wof_render_value(&$property) {
		if (isset($property['_value'])) {
			return $property['_value'];
		} else if (isset($property['default'])) {
			return $property['default'];
		}
		return '';
	}

	########################################################################

	function wof_render_object_id($context) {
		# $context = preg_replace('/[:.]/g', '-', $context);
		$context = preg_replace('/[:.]/', '-', $context);
		return "json-schema-object-$context";
	}

	########################################################################

	function wof_render_array_id($context) {
		# $context = preg_replace('/[:.]/g', '-', $context);
		$context = preg_replace('/[:.]/', '-', $context);
		return "json-schema-array-$context";
	}

	########################################################################

	function wof_render_property_classes($prop) {
		$classes = $GLOBALS['wof_property_classes']['_default'];
		if ($GLOBALS['wof_property_classes'][$prop]) {
			$classes = array_merge(
				$classes,
				$GLOBALS['wof_property_classes'][$prop]
			);
		}
		$class_list = array();
		foreach ($classes as $class => $enabled) {
			if ($enabled) {
				$class_list[] = "property-$class";
			}
		}
		return 'object-property ' . implode(' ', $class_list);
	}

	########################################################################

	function wof_render_type(&$property) {
		if (isset($property['type'])) {
			return $property['type'];
		}
		return 'string';
	}

	########################################################################

	function wof_render_names(&$values){

		// Default languages
		$lang_defaults = $GLOBALS['names_default_languages'];

		$names = array(
			'type' => 'object',
			'properties' => array()
		);
		foreach ($lang_defaults as $lang) {
			$names['properties'][$lang] = array(
				'type' => 'object',
				'properties' => array(
					'preferred' => array(
						'type' => 'array'
					),
					'variant' => array(
						'type' => 'array'
					),
					'colloquial' => array(
						'type' => 'array'
					)
				)
			);
		}

		if (! $values['properties']){
			return $names;
		}

		// Assign names from values
		foreach ($values['properties'] as $key => $value){
			if (preg_match('/^name:(.+)_x_(.+)$/', $key, $matches)){
				list(, $lang, $type) = $matches;
				if (! $names['properties'][$lang]){
					$names['properties'][$lang] = array(
						'type' => 'object',
						'properties' => array()
					);
				}
				$names['properties'][$lang]['properties'][$type] = array(
					'type' => 'array',
					'properties' => array()
				);
				foreach ($value as $item) {
					$names['properties'][$lang]['properties'][$type]['properties'][] = array(
						'type' => 'string',
						'_value' => $item
					);
				}
			}

			if (empty($names['properties'][$lang]['properties']['preferred'])) {
				$names['properties'][$lang]['properties']['preferred'] = array(
					'type' => 'array'
				);
			}
			if (empty($names['properties'][$lang]['properties']['variant'])) {
				$names['properties'][$lang]['properties']['variant'] = array(
					'type' => 'array'
				);
			}
			if (empty($names['properties'][$lang]['properties']['colloquial'])) {
				$names['properties'][$lang]['properties']['colloquial'] = array(
					'type' => 'array'
				);
			}
		}

		// Sort by language
		ksort($names['properties']);

		return $names;
	}

	# the end
