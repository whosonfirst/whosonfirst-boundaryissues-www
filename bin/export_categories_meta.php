<?php

	include(__DIR__ . "/init_local.php");

	setup_categories();
	setup_categories_struct();
	setup_categories_icons();
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

	function setup_categories_icons() {

		// This populates a $categories global.

		global $categories;
		$rsp = db_fetch("
			SELECT *
			FROM boundaryissues_categories_meta
			WHERE name = 'mapzen_icon'
			  AND value != ''
		");
		foreach ($rsp['rows'] as $item) {
			$id = $item['category_id'];
			$categories[$id]['icon'] = $item['value'];
		}
	}

	function export_meta_json() {

		global $categories;
		$categories_meta = array(
			'tags' => array(),
			'icons' => array()
		);

		// Namespace
		foreach ($categories as $id => $item) {
			if ($item['type'] == 'namespace') {
				$namespace_uri = $item['uri'];
				$categories_meta['tags']["$namespace_uri:"] = array();
			}
		}

		// Predicate
		foreach ($categories as $id => $item) {
			if ($item['type'] == 'predicate') {
				$namespace_id = $item['parent_id'];
				$namespace_cat = $categories[$namespace_id];
				$namespace_uri = $namespace_cat['uri'];
				$predicate_uri = $item['uri'];
				$categories_meta['tags']["$namespace_uri:"]["$namespace_uri:$predicate_uri="] = array();
			}
		}

		// Value
		foreach ($categories as $id => $item) {
			if ($item['type'] == 'value') {
				$predicate_id = $item['parent_id'];
				$predicate_cat = $categories[$predicate_id];
				$namespace_id = $predicate_cat['parent_id'];
				$namespace_cat = $categories[$namespace_id];
				$namespace_uri = $namespace_cat['uri'];
				$predicate_uri = $predicate_cat['uri'];
				$value_uri = $item['uri'];
				$tag = "$namespace_uri:$predicate_uri=$value_uri";
				$categories_meta['tags']["$namespace_uri:"]["$namespace_uri:$predicate_uri="][] = $tag;
				if (! empty($item['icon'])) {

					$categories_meta['icons'][$tag] = $item['icon'];
				}
			}
		}

		$categories_json = json_encode($categories_meta);
		file_put_contents(__DIR__ . '/../www/meta/categories.json', $categories_json);
	}
