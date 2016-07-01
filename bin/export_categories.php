<?php

	include(__DIR__ . "/init_local.php");

	if (! $argv[1]) {
		die("Usage: php bin/export_categories.php path/to/dir\n");
	}

	$dir = $argv[1];

	if (! file_exists($dir)) {
		die("Oops, $dir does not exist.\n");
	}

	if (! is_writable($dir)) {
		die("Oops, $dir is not writable.\n");
	}

	setup_categories();
	setup_meta();

	export_categories($dir);

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
			if (! $categories[$type]) {
				$categories[$type] = array();
			}
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

	function export_categories($dir) {
		
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
