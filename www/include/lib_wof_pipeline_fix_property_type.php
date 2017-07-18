<?php

	loadlib('wof_utils');
	loadlib('wof_geojson');
	loadlib('wof_pipeline_utils');

	########################################################################

	function wof_pipeline_fix_property_type_defaults($meta) {
		$defaults = array(
			'branch_merge' => true,
			'user_confirmation' => true
		);
		return array_merge($defaults, $meta);
	}

	########################################################################

	function wof_pipeline_fix_property_type_validate($meta, $names) {

		if (! $meta['repo']) {
			return array(
				'ok' => 0,
				'error' => 'No repo specified.'
			);
		}

		if (! $meta['property']) {
			return array(
				'ok' => 0,
				'error' => 'No property specified.'
			);
		}

		if (! $meta['type']) {
			return array(
				'ok' => 0,
				'error' => 'No type specified.'
			);
		}

		return array(
			'ok' => 1
		);
	}

	########################################################################

	function wof_pipeline_fix_property_type($pipeline, $dry_run = false) {

		$repo_data_path = wof_pipeline_repo_path($pipeline);
		$repo_path = dirname($repo_data_path);
		$bi_root = dirname(dirname(__DIR__));

		$esc_repo = escapeshellarg($repo_path);
		$esc_property = escapeshellarg($pipeline['property']);
		$esc_type = escapeshellarg($pipeline['type']);

		$args = "-repo $esc_repo -property $esc_property -type $esc_type";
		$cmd = "$bi_root/bin/wof-ensure-property $args";

		$output = shell_exec($cmd);
		$output = trim($output);

		$lines = explode("\n", $output);
		$headers = array_shift($lines);
		$updated = array();

		foreach ($lines as $line) {
			$row = str_getcsv($line);
			$wof_id = $row[0];

			if (! $wof_id) {
				continue;
			}
			$path = wof_utils_id2abspath($repo_data_path, $wof_id);
			$geojson = file_get_contents($path);
			$feature = json_decode($geojson);
			$props = $feature->properties;

			$prop = $pipeline['property'];
			$value = $props->$prop;

			if ($pipeline['type'] == 'object') {
				$props->$prop = (object) $props->$prop;
			} else if ($pipeline['type'] == 'array') {
				if (empty($value)) {
					$props->$prop = array();
				} else {
					$props->$prop = array($props->$prop);
				}
			} else if ($pipeline['type'] == 'string') {
				$props->$prop = "$value";
			} else if ($pipeline['type'] == 'number' && is_string($props->$prop)) {
				if (strpos($props->$prop, '.') === false) {
					$props->$prop = intval($props->$prop);
				} else {
					$props->$prop = floatval($props->$prop);
				}
			} else {
				return array(
					'ok' => 0,
					'error' => "Uncertain how to handle $wof_id property '$prop'"
				);
			}

			$geojson = json_encode($feature);

			$rsp = wof_geojson_encode($geojson);
			if (! $rsp['ok']) {
				return $rsp;
			}

			$geojson = $rsp['encoded'];
			$filename = basename($path);
			$updated[$filename] = $geojson;

			if (! $dry_run) {
				file_put_contents($path, $geojson);
			}
		}

		return array(
			'ok' => 1,
			'cmd' => $cmd,
			'summary' => $output,
			'updated' => $updated
		);
	}
