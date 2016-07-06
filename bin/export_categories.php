<?php

	include(__DIR__ . "/init_local.php");

	if (! $argv[1]) {
		die("Usage: php bin/export_categories.php path/to/dir\n");
	}

	setup_categories();
	setup_meta();

	if (substr($argv[1], -4, 4) == '.csv') {

		export_categories_csv($argv[1]);

	} else {

		$dir = $argv[1];

		if (! file_exists($dir)) {
			die("Oops, $dir does not exist.\n");
		}

		if (! is_writable($dir)) {
			die("Oops, $dir is not writable.\n");
		}

		export_categories_json($dir);
	}

	function setup_categories() {

		// This populates a $categories global.

		global $categories;
		$categories = array();
		$rsp = db_fetch("
			SELECT *
			FROM boundaryissues_categories
		");
		foreach ($rsp['rows'] as $item) {
			$id = $item['id'];
			$categories[$id] = $item;
		}
	}

	function setup_meta() {

		// This decorates the $categories global with meta values.

		global $categories;
		$rsp = db_fetch("
			SELECT *
			FROM boundaryissues_categories_meta
		");
		foreach ($rsp['rows'] as $item) {
			$id = $item['category_id'];
			$name = $item['name'];
			$value = $item['value'];
			$categories[$id][$name] = $value;
		}
	}

	function export_categories_json($dir) {

		// Exports each category item to a JSON file.

		global $categories;

		foreach ($categories as $cat) {
			$type = $cat['type'];
			$uri = $cat['uri'];
			$path = "$dir/$type/$uri.json";
			$json = json_encode(array(
				'id' => intval($cat['id']),
				'name' => $uri,
				'label' => $cat['label_en']
			), JSON_PRETTY_PRINT);
			if (! file_exists("$dir/$type")) {
				mkdir("$dir/$type", 0755, true);
			}
			file_put_contents($path, $json);
		}
	}

	function export_categories_csv($filename) {

		// Exports the categories to a CSV file.

		global $categories;

		foreach ($categories as $cat) {
			if ($cat['type'] == 'value') {
				$columns = array_keys($cat);
				break;
			}
		}

		$csv = fopen($filename, 'w');

		fputcsv($csv, $columns);
		foreach ($categories as $cat) {
			fputcsv($csv, $cat);
		}

		fclose($csv);
	}
