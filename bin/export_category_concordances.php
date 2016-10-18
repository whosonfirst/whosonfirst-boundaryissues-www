<?php

	include(__DIR__ . "/init_local.php");

	if ($argc < 2) {
		die("Usage: php bin/export_category_concordances.php [path to whosonfirst-categories]\n");
	}

	$path = $argv[1];
	if (! file_exists($path)) {
		die("Usage: php bin/export_category_concordances.php [path to whosonfirst-categories]\n");
	}

	setup_categories();
	export_concordances('osm', $path);
	export_concordances('sg', $path);

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
		$rsp = db_fetch("
			SELECT *
			FROM boundaryissues_categories_struct
		");
		foreach ($rsp['rows'] as $item) {
			$id = $item['source_id'];
			$categories[$id]['parent'] = intval($item['target_id']);
		}
	}

	function export_concordances($prefix, $path) {

		$file = "$path/concordances/{$prefix}_mz.csv";
		$prefix_len = strlen($prefix);

		$rsp = db_fetch("
			SELECT category_id, name, value
			FROM boundaryissues_categories_meta
			WHERE SUBSTR(name, 1, $prefix_len) = '$prefix'
			  AND value != ''
		");
		if (! $rsp['ok']) {
			print_r($rsp);
			exit;
		}

		$cats = array();
		foreach ($rsp['rows'] as $row) {
			$id = $row['category_id'];
			if (empty($cats[$id])) {
				$cats[$id] = array();
			}
			$name = $row['name'];
			$cats[$id][$name] = $row['value'];
		}

		$fh = fopen($file, 'w');
		fputcsv($fh, array(
			"$prefix:machinetag",
			"mz:machinetag"
		));

		foreach ($cats as $id => $meta) {
			$concordance = null;
			if ($prefix == 'osm') {
				if ($meta['osm_key'] && $meta['osm_value']) {
					$concordance = "osm:{$meta['osm_key']}={$meta['osm_value']}";
				} else {
					echo "Could not decipher $prefix machinetag for $id\n";
				}
			} else if ($prefix == 'sg') {
				if ($meta['sg_type'] && $meta['sg category'] && $meta['sg subcategory']) {
					$concordance = "{$meta['sg_type']}:{$meta['sg category']}={$meta['sg subcategory']}";
				} else {
					echo "Could not decipher $prefix machinetag for $id\n";
					//print_r($meta);
				}
			}

			$mz_tag = get_machinetag($id);
			if ($concordance) {
				fputcsv($fh, array(
					$concordance,
					$mz_tag
				));
			}
		}
		fclose($fh);
	}

	function get_machinetag($id) {
		global $categories;
		$value = $categories[$id]['uri'];
		$predicate_id = $categories[$id]['parent'];
		$predicate = $categories[$predicate_id]['uri'];
		$namespace_id = $categories[$predicate_id]['parent'];
		$namespace = $categories[$namespace_id]['uri'];
		return "$namespace:$predicate=$value";
	}
