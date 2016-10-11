<?php

	include(__DIR__ . "/init_local.php");
	loadlib('artisanal_integers');

	if (! $argv[1]) {
		die("Usage: php bin/export_categories.php path/to/dir\n");
	}

	setup_categories();
	setup_meta();
	setup_struct();

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

	function setup_struct() {

		// This decorates the $categories global with struct values.

		global $categories;
		$rsp = db_fetch("
			SELECT *
			FROM boundaryissues_categories_struct
			WHERE type = 'parent'
		");
		foreach ($rsp['rows'] as $item) {
			$id = $item['source_id'];
			$categories[$id]['parent_id'] = intval($item['target_id']);
		}
	}

	function export_categories_json($dir) {

		// Exports each category item to a JSON file.

		global $categories;

		foreach ($categories as $cat) {

			if ($cat['id'] < 5000) {

				// This is a weird situation. Basically the DB
				// has auto-increment IDs assigned, but our JSON
				// has artisanal integers. Update the DB with
				// newly minted integers.

				$old_id = $cat['id'];

				// Mint an artisanal integer
				$rsp = artisanal_integers_create();
				if (! $rsp['ok']) {
					return $rsp;
				}
				$id = $rsp['integer'];
				$cat['id'] = $id;

				echo "Updating ID $old_id to $id\n";

				$rsp = db_update('boundaryissues_categories', array(
					'id' => $id
				), "id = $old_id");
				if (! $rsp['ok']) {
					return $rsp;
				}

				$rsp = db_update('boundaryissues_categories_meta', array(
					'category_id' => $id
				), "category_id = $old_id");
				if (! $rsp['ok']) {
					return $rsp;
				}

				$rsp = db_update('boundaryissues_categories_struct', array(
					'source_id' => $id
				), "source_id = $old_id");
				if (! $rsp['ok']) {
					return $rsp;
				}

				$rsp = db_update('boundaryissues_categories_struct', array(
					'target_id' => $id
				), "target_id = $old_id");
				if (! $rsp['ok']) {
					return $rsp;
				}
			}

			$type = $cat['type'];
			$uri = $cat['uri'];
			$path = "$dir/$type/$uri.json";

			$category = array(
				'id' => intval($cat['id']),
				'name' => $uri,
				'label' => $cat['label_en']
			);

			if (! empty($cat['rank'])) {
				$category['mz:rank'] = intval($cat['rank']);
			}

			if (! empty($cat['parent_id'])) {
				$category['parent_id'] = $cat['parent_id'];
			}

			$json = json_encode($category, JSON_PRETTY_PRINT);
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
