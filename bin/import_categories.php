<?php

	include(__DIR__ . "/init_local.php");
	loadlib('artisanal_integers');

	// This is all designed around a CSV file that's included in the schema
	// directory: categories.csv. It's designed to run iteratively to
	// pull in updates to the CSV, and eventually transition to managing
	// the data via a web UI and export out JSON files for public use.
	//
	// This isn't about WOF records, it's just a way to manage (one example
	// of) a controlled taxonomy. It takes records from a CSV, assigns
	// stable IDs, and structures them in a relational db for later use.
	// (20160701/dphiffer)

	if (! $argv[1]) {
		die("Usage: php bin/import_categories.php target [start]\n  - (required) target can be either .csv or a JSON schema directory\n  - (optional) start is the .csv row number to skip to");
	}

	// This is all pretty straightforward: we populate a couple globals
	// that reflect state in the current database...
	setup_categories();

	if (substr($argv[1], -4, 4) == '.csv') {

		$start = 0;
		if ($argv[2]) {
			$start = intval($argv[2]);
		}

		// And then we check the CSV file for anything new.
		$rsp = import_from_csv($argv[1], $start);
		if (! $rsp['ok']) {
			die(var_export($rsp) . "\n");
		}

	} else {

		// ... Or we iterate over a JSON schema directory tree
		$rsp = import_from_json($argv[1]);
		if (! $rsp['ok']) {
			die(var_export($rsp) . "\n");
		}

	}

	function import_from_csv($filename, $start = 0) {

		$fh = fopen($filename, 'r');

		// The first row is assumed to be column names
		$cols = fgetcsv($fh);
		$row_num = 0;

		while ($data = fgetcsv($fh)) {

			$row_num++;

			// $start lets us skip to a specific row in the CSV
			if ($row_num < $start) {
				continue;
			}

			$row = array();

			// Use the first row to assign named properties
			foreach ($cols as $index => $col) {
				$row[$col] = $data[$index];
			}

			// Then do some other stuff....
			$rsp = import_row($row);
			if (! $rsp['ok']) {
				$rsp['row_num'] = $row_num; // where did we leave off?
				return $rsp;
			}
		}

		return array('ok' => 1);
	}

	function import_from_json($dir) {

		$rsp = import_from_json_type_dir($dir, 'namespace');
		if (! $rsp['ok']) {
			return $rsp;
		}

		$rsp = import_from_json_type_dir($dir, 'predicate');
		if (! $rsp['ok']) {
			return $rsp;
		}

		$rsp = import_from_json_type_dir($dir, 'value');
		if (! $rsp['ok']) {
			return $rsp;
		}

		return array('ok' => 1);
	}

	function import_from_json_type_dir($dir, $type) {

		if (! file_exists("$dir/$type")) {
			return array(
				'ok' => 0,
				'error' => "No '$type' folder found."
			);
		}

		$files = glob("$dir/$type/*.json");
		foreach ($files as $file) {
			$json = file_get_contents($file);
			$item = json_decode($json, 'as hash');
			$rsp = import_json_item($item, $type);
			if (! $rsp['ok']) {
				return $rsp;
			}
		}

		return array('ok' => 1);
	}

	function setup_categories() {

		// This populates a $categories global that we can use to
		// find existing categories.

		global $categories;
		$categories = array();
		$existing_categories = db_fetch("
			SELECT *
			FROM boundaryissues_categories
		");
		foreach ($existing_categories['rows'] as $item) {
			$type = $item['type'];
			$uri = $item['uri'];
			if (! $categories[$type]) {
				$categories[$type] = array();
			}
			$categories[$type][$uri] = $item['id'];
		}
	}

	function import_row($row) {

		// The category types are stored with this structure in mind:
		//    namespace:predicate = value

		// I'm ignoring "aliases" and "groups" for now, but we can get
		// to those later.

		// We'll import the high-level types first.

		// Namespace
		$rsp = import_category($row, 'namespace');
		if (! $rsp['ok']) {
			return $rsp;
		}
		$namespace_id = $rsp['id'];

		// Predicate
		$rsp = import_category($row, 'predicate');
		if (! $rsp['ok']) {
			return $rsp;
		}
		$predicate_id = $rsp['id'];

		// The value
		$rsp = import_category($row, 'value');
		if (! $rsp['ok']) {
			return $rsp;
		}
		$value_id = $rsp['id'];

		// Reset meta and struct tables for each imported category
		$esc_namespace_id = intval($namespace_id);
		$esc_predicate_id = intval($predicate_id);
		$esc_value_id = intval($value_id);
		db_write("
			DELETE FROM boundaryissues_categories_meta
			WHERE category_id IN ($esc_namespace_id, $esc_predicate_id, $esc_value_id)
		");
		db_write("
			DELETE FROM boundaryissues_categories_struct
			WHERE source_id IN ($esc_namespace_id, $esc_predicate_id, $esc_value_id)
		");

		// The 'label_en' property is the English name. Maybe this
		// should be called something else, along the lines of BCP-47?

		$rsp = category_meta($namespace_id, 'label_en', $row['namespace']);
		if (! $rsp['ok']) {
			return $rsp;
		}

		$rsp = category_meta($predicate_id, 'label_en', $row['predicate']);
		if (! $rsp['ok']) {
			return $rsp;
		}

		$rsp = category_meta($value_id, 'label_en', $row['value']);
		if (! $rsp['ok']) {
			return $rsp;
		}

		// We don't need to do anything with these columns, since they
		// are already reflected in the data.
		$ignore_cols = array(
			'namespace', 'namespace_uri', 'namespace_rank',
			'predicate', 'predicate_uri', 'predicate_rank',
			'value', 'value_uri', 'value_rank'
		);

		foreach ($row as $key => $value) {

			// Iterate over all the columns.

			if (! in_array($key, $ignore_cols)) {
				$rsp = category_meta($value_id, $key, $value);
				if (! $rsp['ok']) {
					return $rsp;
				}
			}
		}

		category_structure($predicate_id, 'predicate', $row);
		category_structure($value_id, 'value', $row);

		return array('ok' => 1);
	}

	function import_category($row, $type) {

		global $categories;

		$uri = $row["{$type}_uri"];
		$rank_col = "{$type}_rank";

		$category = array(
			'type' => $type,
			'uri' => addslashes($uri)
		);

		// Add a rank value.
		if ($rank_col && $row[$rank_col]) {
			$category['rank'] = addslashes($row[$rank_col]);
		}

		if (empty($categories[$type][$uri])) {

			// Mint an artisanal integer
			// (Use auto_increment for testing)
			//$rsp = artisanal_integers_create();
			//if (! $rsp['ok']) {
			//	return $rsp;
			//}
			//$category['id'] = $rsp['integer'];

			// Insert it!
			$rsp = db_insert('boundaryissues_categories', $category);
			if (! $rsp['ok']) {
				return $rsp;
			}

			// Cache it!
			//$id = $category['id'];
			$id = $rsp['insert_id'];
			if (! $categories[$type]) {
				$categories[$type] = array();
			}
			$categories[$type][$uri] = $id;

		} else {

			// Look up the ID
			$id = $categories[$type][$uri];

			// Update it!
			$where = "id = " . addslashes($id);
			$rsp = db_update('boundaryissues_categories', $category, $where);
			if (! $rsp['ok']) {
				return $rsp;
			}
		}

		return array(
			'ok' => 1,
			'id' => $id
		);
	}

	function import_json_item($item, $type) {

		global $categories;

		$id = intval($item['id']);
		$uri = $item['name'];
		$label = $item['label'];

		$category = array(
			'id' => $id,
			'type' => addslashes($type),
			'uri' => addslashes($uri)
		);

		if ($categories[$type][$uri]) {
			$where = "id = " . addslashes($id);
			$rsp = db_update('boundaryissues_categories', $category, $where);
			if (! $rsp['ok']) {
				return $rsp;
			}
		} else {
			$rsp = db_insert('boundaryissues_categories', $category);
			if (! $rsp['ok']) {
				return $rsp;
			}
		}

		$rsp = category_meta($id, 'label_en', $label);
		if (! $rsp['ok']) {
			return $rsp;
		}

		return array('ok' => 1);
	}

	function category_meta($category_id, $name, $value) {

		// This assumes that the meta table has been reset already

		$rsp = db_insert('boundaryissues_categories_meta', array(
			'category_id' => addslashes($category_id),
			'name' => addslashes($name),
			'value' => addslashes($value)
		));

		if (! $rsp['ok']) {
			return $rsp;
		}

		return array('ok' => 1);

	}

	function category_structure($id, $type, $row) {

		// This assumes that the struct table has been reset already

		global $categories;

		if ($type == 'predicate') {
			$parent_uri = $row['namespace_uri'];
			$parent_id = $categories['namespace'][$parent_uri];
			$rsp = db_insert('boundaryissues_categories_struct', array(
				'source_id' => addslashes($id),
				'target_id' => addslashes($parent_id),
				'type' => 'parent'
			));
			if (! $rsp['ok']) {
				return $rsp;
			}
		} else if ($type == 'value') {
			$parent_uri = $row['predicate_uri'];
			$parent_id = $categories['predicate'][$parent_uri];
			$rsp = db_insert('boundaryissues_categories_struct', array(
				'source_id' => addslashes($id),
				'target_id' => addslashes($parent_id),
				'type' => 'parent'
			));
			if (! $rsp['ok']) {
				return $rsp;
			}
		}

		return array('ok' => 1);


	}