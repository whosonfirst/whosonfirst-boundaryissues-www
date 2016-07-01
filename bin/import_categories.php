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

	// This is all pretty straightforward: we populate a couple globals
	// that reflect state in the current database...
	setup_categories();
	setup_meta();

	// And then we check the CSV file for anything new.
	import_from_csv($argv[1]);

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
			import_row($row);
		}
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

		// We'll import the high-level categories first. These exist
		// in the same database table, but with a different 'type.'

		// Basically there are two hierarchies:
		//    domain -> subdomain -> category
		//    group -> category

		// I'm ignoring "aliases" for now, but we can get to them later.

		$rsp = import_category($row, 'domain');
		if (! $rsp['ok']) {
			return $rsp;
		}
		$domain_id = $rsp['id'];

		$rsp = import_category($row, 'subdomain');
		if (! $rsp['ok']) {
			return $rsp;
		}
		$subdomain_id = $rsp['id'];

		$rsp = import_category($row, 'group');
		if (! $rsp['ok']) {
			return $rsp;
		}
		$group_id = $rsp['id'];

		// Then the "category" category.
		$rsp = import_category($row, 'category');
		if (! $rsp['ok']) {
			return $rsp;
		}
		$category_id = $rsp['id'];

		// The 'label_en' property is the English name. Maybe this
		// should be called something else, along the lines of BCP-47?

		category_meta($category_id, 'label_en', $row['name']);
		category_meta($subdomain_id, 'label_en', $row['subdomain']);
		category_meta($domain_id, 'label_en', $row['domain']);
		category_meta($group_id, 'label_en', $row['group']);

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
				category_meta($category_id, $key, $value);
			}
		}
	}

	function import_category($row, $type) {

		global $categories;

		// Note: I'm using the type "category" instead of the CSV file
		// convention of "name." The word "name" just seems too generic.

		if ($type == 'category') {
			$uri = $row["name_uri"];
			$rank_col = "rank4";
		} else {
			$uri = $row["{$type}_uri"];
			$rank_col = "{$type}_rank";
		}

		// This is what we're inserting into the DB.
		$category = array(
			'type' => addslashes($type),
			'uri' => addslashes($uri)
		);

		// Add a rank value.
		if ($rank_col && $row[$rank_col]) {
			$category['rank'] = addslashes($row[$rank_col]);
		}

		// Add in domain metadata.
		if ($type == 'category' || $type == 'subdomain') {
			$domain_uri = $row['domain_uri'];
			$category['domain_id'] = $categories['domain'][$domain_uri];
			$category['domain_uri'] = $domain_uri;
			$category['domain_rank'] = $row['domain_rank'];
		}

		// Add in subdomain and group metadata.
		if ($type == 'category') {
			$subdomain_uri = $row['subdomain_uri'];
			$category['subdomain_id'] = $categories['subdomain'][$subdomain_uri];
			$category['subdomain_uri'] = $subdomain_uri;
			$category['subdomain_rank'] = $row['subdomain_rank'];
			$group_uri = $row['group_uri'];
			$category['group_id'] = $categories['group'][$group_uri];
			$category['group_uri'] = $group_uri;
			$category['group_rank'] = $row['group_rank'];
		}

		if (empty($categories[$type][$uri])) {

			// Mint an artisanal integer
			$rsp = artisanal_integers_create();
			if (! $rsp['ok']) {
				return $rsp;
			}
			$category['id'] = $rsp['integer'];

			// Insert it!
			$rsp = db_insert('boundaryissues_categories', $category);
			if (! $rsp['ok']) {
				return $rsp;
			}

			// Cache it!
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

	function category_meta($category_id, $name, $value) {

		global $meta;

		if (! isset($meta[$category_id][$name])) {
			db_insert('boundaryissues_categories_meta', array(
				'category_id' => addslashes($category_id),
				'name' => addslashes($name),
				'value' => addslashes($value)
			));
		} else {
			$where = "category_id = " . addslashes($category_id);
			db_update('boundaryissues_categories_meta', array(
				'name' => addslashes($name),
				'value' => addslashes($value)
			), $where);
		}

		if (! $meta[$category_id]) {
			$meta[$category_id] = array();
		}
		$meta[$category_id][$name] = $value;
	}
