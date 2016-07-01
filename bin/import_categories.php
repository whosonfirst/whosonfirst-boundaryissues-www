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
		die("Usage: php bin/import_categories.php schema/categories.csv\n");
	}

	// The CSV file refers to things differently:
	$type_translation = array(
		'domain' => 'namespace',
		'subdomain' => 'predicate',
		'name' => 'value'
	);

	// This is all pretty straightforward: we populate a couple globals
	// that reflect state in the current database...
	setup_categories();
	setup_meta();

	// And then we check the CSV file for anything new.
	$rsp = import_from_csv($argv[1]);
	if (! $rsp['ok']) {
		die(var_export($rsp) . "\n");
	}

	function import_from_csv($filename) {

		$fh = fopen($filename, 'r');

		// The first row is assumed to be column names
		$cols = fgetcsv($fh);

		while ($data = fgetcsv($fh)) {
			$row = array();

			// Use the first row to assign named properties
			foreach ($cols as $index => $col) {
				$row[$col] = $data[$index];
			}

			// Then do some other stuff....
			$rsp = import_row($row);
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

	function setup_meta() {

		// This populates a $meta global that we can use to
		// find meta properties for each category.

		global $meta;
		$meta = array();
		$existing_meta = db_fetch("
			SELECT *
			FROM boundaryissues_categories_meta
		");
		foreach ($existing_meta['rows'] as $item) {
			$id = $item['category_id'];
			$name = $item['name'];
			$value = $item['value'];
			if (! $meta[$id]) {
				$meta[$id] = array();
			}
			$meta[$id][$name] = $value;
		}
	}

	function import_row($row) {

		// The category types are stored with this structure in mind:
		//    namespace:predicate = value

		// I'm ignoring "aliases" and "groups" for now, but we can get
		// to those later.

		// We'll import the high-level types first.

		// Namespace (aka domain)
		$rsp = import_category($row, 'domain');
		if (! $rsp['ok']) {
			return $rsp;
		}
		$namespace_id = $rsp['id'];

		// Predicate (aka subdomain)
		$rsp = import_category($row, 'subdomain');
		if (! $rsp['ok']) {
			return $rsp;
		}
		$predicate_id = $rsp['id'];

		// The value (aka name)
		$rsp = import_category($row, 'name');
		if (! $rsp['ok']) {
			return $rsp;
		}
		$value_id = $rsp['id'];

		// The 'label_en' property is the English name. Maybe this
		// should be called something else, along the lines of BCP-47?

		$rsp = category_meta($namespace_id, 'label_en', $row['domain']);
		if (! $rsp['ok']) {
			return $rsp;
		}

		$rsp = category_meta($predicate_id, 'label_en', $row['subdomain']);
		if (! $rsp['ok']) {
			return $rsp;
		}

		$rsp = category_meta($value_id, 'label_en', $row['name']);
		if (! $rsp['ok']) {
			return $rsp;
		}

		// We don't need to do anything with these columns, since they
		// are already reflected in the data.
		$ignore_cols = array(
			'name', 'name_uri', 'rank4',
			'subdomain', 'subdomain_uri', 'subdomain_rank',
			'domain', 'domain_uri', 'domain_rank',
			'group', 'group_uri', 'group_rank'
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
		
		return array('ok' => 1);
	}

	function import_category($row, $type) {

		global $categories, $type_translation;

		$uri = $row["{$type}_uri"];
		$rank_col = "{$type}_rank";

		if ($type == 'name') {
			$rank_col = "rank4";
		}

		// We are storing things machinetags-style
		$translated_type = $type_translation[$type];

		// This is what we're inserting into the DB.
		$category = array(
			'type' => $translated_type,
			'uri' => addslashes($uri)
		);

		// Add a rank value.
		if ($rank_col && $row[$rank_col]) {
			$category['rank'] = addslashes($row[$rank_col]);
		}

		// Add in namespace metadata.
		if ($type == 'name' || $type == 'subdomain') {
			$namespace_uri = $row['domain_uri'];
			$category['namespace_id'] = $categories['namespace'][$namespace_uri];
			$category['namespace_uri'] = $namespace_uri;
			$category['namespace_rank'] = $row['domain_rank'];
		}

		// Add in predicate metadata.
		if ($type == 'name') {
			$predicate_uri = $row['subdomain_uri'];
			$category['predicate_id'] = $categories['predicate'][$predicate_uri];
			$category['predicate_uri'] = $predicate_uri;
			$category['predicate_rank'] = $row['subdomain_rank'];
		}
		
		if (empty($categories[$translated_type][$uri])) {

			// Mint an artisanal integer
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
			$id = $rsp['insert_id'];
			if (! $categories[$translated_type]) {
				$categories[$translated_type] = array();
			}
			$categories[$translated_type][$uri] = $id;

		} else {

			// Look up the ID
			$id = $categories[$translated_type][$uri];

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

	function category_meta($category_id, $name, $value) {

		global $meta;

		if (! isset($meta[$category_id][$name])) {
			$rsp = db_insert('boundaryissues_categories_meta', array(
				'category_id' => addslashes($category_id),
				'name' => addslashes($name),
				'value' => addslashes($value)
			));
		} else {
			$where = "category_id = " . addslashes($category_id);
			$rsp = db_update('boundaryissues_categories_meta', array(
				'name' => addslashes($name),
				'value' => addslashes($value)
			), $where);
		}
		
		if (! $rsp['ok']) {
			return $rsp;
		}

		if (! $meta[$category_id]) {
			$meta[$category_id] = array();
		}
		$meta[$category_id][$name] = $value;
		
		return array('ok' => 1);

	}
