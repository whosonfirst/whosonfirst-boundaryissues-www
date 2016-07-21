<?php

	include(__DIR__ . "/init_local.php");

	setup_categories();
	setup_categories_struct();
	export_meta_json();

	function setup_categories() {

		// This populates a $categories global.

		global $categories;
		$categories = array();
		$rsp = db_fetch("
			SELECT *
			FROM boundaryissues_categories
			ORDER BY rank
		");
		foreach ($rsp['rows'] as $item) {
			$id = $item['id'];
			$categories[$id] = $item;
		}
	}

	function setup_categories_struct() {

		// This populates a $categories global.

		global $categories;
		$rsp = db_fetch("
			SELECT *
			FROM boundaryissues_categories_struct
		");
		foreach ($rsp['rows'] as $item) {
			$id = $item['source_id'];
			$categories[$id]['parent_id'] = $item['target_id'];
		}
	}

	function export_meta_json() {

		global $categories;
		$machine_tags = array();

		// Namespace
		foreach ($categories as $id => $item) {
			if ($item['type'] == 'namespace') {
				$machine_tags[] = "{$item['uri']}:";
			}
		}

		// Predicate
		foreach ($categories as $id => $item) {
			if ($item['type'] == 'predicate') {
				$parent_id = $item['parent_id'];
				$parent = $categories[$parent_id];
				$machine_tags[] = "{$parent['uri']}:{$item['uri']}=";
			}
		}

		// Value
		foreach ($categories as $id => $item) {
			if ($item['type'] == 'value') {
				$parent_id = $item['parent_id'];
				$parent = $categories[$parent_id];
				$grandparent_id = $parent['parent_id'];
				$grandparent = $categories[$grandparent_id];
				$machine_tags[] = "{$grandparent['uri']}:{$parent['uri']}={$item['uri']}";
			}
		}

		$categories_json = json_encode($machine_tags);
		file_put_contents(__DIR__ . '/../www/meta/categories.json', $categories_json);
	}
