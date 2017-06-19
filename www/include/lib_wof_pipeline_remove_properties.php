<?php

	loadlib('wof_save');

	########################################################################

	function wof_pipeline_remove_properties_defaults($meta) {
		$defaults = array(
			'branch_merge' => true
		);
		return array_merge($defaults, $meta);
	}

	########################################################################

	function wof_pipeline_remove_properties_validate($meta, $names) {

		$errors = array();

		if (! $meta['property_list']) {
			$errors[] = "No 'property_list' declared in meta.json.";
		}

		if (! in_array('remove_properties.csv', $names)) {
			$errors[] = "remove_properties.csv not found in zip file.";
		}

		if (count($errors) == 0) {
			return array(
				'ok' => 1
			);
		} else {
			return array(
				'ok' => 0,
				'errors' => $errors
			);
		}
	}

	########################################################################

	function wof_pipeline_remove_properties_repo($upload, $files) {

		$files = array('remove_properties.csv');
		$rsp = wof_pipeline_read_zip_contents($upload, $files);
		if (! $rsp['ok']) {
			return $rsp;
		}

		if (! $rsp['contents']['remove_properties.csv']) {
			return array(
				'ok' => 0,
				'error' => 'Could not read remove_properties.csv'
			);
		}

		$lines = explode("\n", $rsp['contents']['remove_properties.csv']);
		$first_line = str_getcsv($lines[0]);

		if (is_numeric($first_line[0])) {
			// No header row
			$wof_id = intval($first_line[0]);
		} else if ($lines[1]) {
			$second_line = str_getcsv($lines[1]);
			if (is_numeric($second_line[0])) {
				$wof_id = intval($second_line[0]);
			}
		}

		if (! $wof_id) {
			return array(
				'ok' => 0,
				'error' => 'Could not find a WOF ID in the first column of remove_properties.csv ' . $debug
			);
		}

		$wof_id = intval($wof_id);
		$repo_path = wof_utils_id2repopath($wof_id);

		if (! $repo_path) {
			return array(
				'ok' => 0,
				'error' => 'Could not find a repo path for WOF ID ' . $wof_id
			);
		}

		return array(
			'ok' => 1,
			'repo' => basename(dirname($repo_path))
		);
	}

	########################################################################

	function wof_pipeline_remove_properties($pipeline, $dry_run = false) {

		$dir = $pipeline['dir'];
		$csv_path = "$dir/remove_properties.csv";
		$property_list = explode(',', $pipeline['meta']['property_list']);
		array_walk($property_list, 'trim');

		if (! file_exists($csv_path)) {
			return array(
				'ok' => 0,
				'error' => 'Could not find remove_properties.csv.'
			);
		}

		$fh = fopen($csv_path, 'r');
		if (! $fh) {
			return array(
				'ok' => 0,
				'error' => 'Could not open remove_properties.csv.'
			);
		}

		$headers = fgetcsv($fh);
		if (is_int($headers[0])) {
			// This is a sign the first row isn't actually headers
			$ids[] = $headers[0];
		}

		while ($row = fgetcsv($fh)) {
			$ids[] = $row[0];
		}

		$updated = array();
		$warnings = array();
		foreach ($ids as $id) {
			$path = wof_utils_find_id($id);
			if (! $path) {
				return array(
					'ok' => 0,
					'error' => 'Could not find WOF ID ' . $id
				);
			}

			$json = file_get_contents($path);
			$feature = json_decode($json, 'as hash');
			if (! $feature) {
				return array(
					'ok' => 0,
					'error' => 'Could not parse WOF ID ' . $id
				);
			}

			$props = $feature['properties'];

			$removed_prop = false;
			foreach ($property_list as $prop) {
				$rsp = wof_pipeline_remove_property($props, $prop);
				if (! $rsp['ok']) {
					$warnings[] = $rsp['error'];
				} else {
					$props = $rsp['properties'];
					$removed_prop = true;
				}
			}

			if ($removed_prop) {
				$feature['properties'] = $props;
				$geojson = json_encode($feature);
				$rsp = wof_geojson_encode($geojson);

				if (! $rsp['ok']) {
					$rsp['error'] = "Error from GeoJSON service: {$rsp['error']}";
					return $rsp;
				}

				if (! $dry_run) {
					$geojson = $rsp['encoded'];
					file_put_contents($path, $geojson);
				}

				$updated[] = $path;
			}
		}

		$rsp = array(
			'ok' => 1,
			'updated' => $updated
		);

		if ($warnings) {
			$rsp['warnings'] = $warnings;
		}

		return $rsp;
	}

	########################################################################

	function wof_pipeline_remove_property($properties, $name) {

		// We use dot notation to indicate a nested property structure,
		// the same way jq does. e.g., wof:concordances.4sq:id
		$path = explode('.', $name);
		$step = array_shift($path);

		if (count($path) == 0) {
			// Leaf of the property path
			if (isset($properties[$step])) {
				// Leaf exists, remove it
				unset($properties[$step]);
				return array(
					'ok' => 1,
					'properties' => $properties
				);
			} else {
				// Leaf does not exist
				return array(
					'ok' => 0,
					'error' => "Could not find property $step"
				);
			}
		} else if (isset($properties[$step])) {
			// Follow the next step in the tree
			$sub_props = $properties[$step];
			$sub_path = implode('.', $path);
			$rsp = wof_pipeline_remove_property($sub_props, $sub_path);
			if (! $rsp['ok']) {
				return $rsp;
			}
			$properties[$step] = $rsp['properties'];
			return array(
				'ok' => 1,
				'properties' => $properties
			);
		} else {
			// Cannot follow the next step in the tree
			return array(
				'ok' => 0,
				'error' => "Could not find sub-property $step"
			);
		}
	}

	# the end
