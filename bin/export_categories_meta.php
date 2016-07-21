<?php

	include(__DIR__ . "/init_local.php");

	setup_categories();
	setup_categories_struct();
	setup_categories_meta();
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
			$categories[$id]['parent_id'] = intval($item['target_id']);
		}
	}

	function setup_categories_meta() {

		// This populates a $categories global.

		global $categories;
		$meta_keys = array(
			'mapzen_icon' => 'icon',
			'label_en' => 'label'
		);
		$rsp = db_fetch("
			SELECT *
			FROM boundaryissues_categories_meta
			WHERE (name = 'mapzen_icon' OR name = 'label_en')
			  AND value != ''
		");
		foreach ($rsp['rows'] as $item) {
			$id = $item['category_id'];
			$name = $item['name'];
			$key = $meta_keys[$name];
			if ($categories[$id]) {
				$categories[$id][$key] = $item['value'];
			}
		}
	}

	function export_meta_json() {

		global $categories;
		$categories_meta = array(
			'namespace' => array(),
			'predicate' => array(),
			'value' => array(),
			'detail' => array(),
			'icon' => array()
		);

		foreach ($categories as $id => $item) {
			$type = $item['type'];
			$id = intval($item['id']);
			unset($item['type']);
			unset($item['id']);
			$item['rank'] = intval($item['rank']);
			$categories_meta[$type][$id] = $item;
			if (! empty($item['icon'])) {
				$parent_id = $item['parent_id'];
				$parent_cat = $categories[$parent_id];
				$grandparent_id = $parent_cat['parent_id'];
				$grandparent_cat = $categories[$grandparent_id];
				$namespace = $grandparent_cat['uri'];
				$predicate = $parent_cat['uri'];
				$value = $item['uri'];
				$tag = "$namespace:$predicate=$value";
				$categories_meta['icons'][$tag] = $item['icon'];
			}
		}

		$categories_json = json_encode($categories_meta);
		file_put_contents(__DIR__ . '/../www/meta/categories.json', $categories_json);
	}
