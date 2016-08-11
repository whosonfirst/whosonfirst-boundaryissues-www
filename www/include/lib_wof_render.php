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
			'deletable' => true
		),
		'wof:id' => array(
			'editable' => false
		),
		'wof:placetype' => array(
			'editable' => false
		),
		'wof:name' => array(
			'deletable' => false
		),
		'wof:parent_id' => array(
			'editable' => false
		),
		'wof:hierarchy' => array(
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
					if (preg_match('/^[0-9]+$/', $value)) {
						$type = 'integer';
					} else if (is_numeric($value)) {
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

	# the end
