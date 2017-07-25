<?php

	loadlib('http');
	loadlib('users_settings');
	loadlib('wof_elasticsearch');
	loadlib('wof_repo');

	########################################################################

	function wof_utils_id2relpath($id, $more=array()){

		$fname = wof_utils_id2fname($id, $more);
		$tree = wof_utils_id2tree($id);

		return implode(DIRECTORY_SEPARATOR, array($tree, $fname));
	}

	########################################################################

	// wof_utils_id2abspath returns an absolute path to the WOF ID,
	// regardless of whether the file exists. This, of course, is necessary
	// for the first time you write the file to disk.

	// See also: wof_utils_find_id

	function wof_utils_id2abspath($root, $id, $more=array()){

		$rel = wof_utils_id2relpath($id, $more);

		// Check $root for a trailing slash, so we don't get two slashes
		if (substr($root, -1, 1) == DIRECTORY_SEPARATOR) {
			$root = substr($root, 0, -1);
		}
		return implode(DIRECTORY_SEPARATOR, array($root, $rel));
	}

	########################################################################

	// wof_utils_find_id checks a sequence of possible root directories
	// until it finds an absolute path for the WOF record. Returns null
	// if no existing file was found.

	// See also: wof_utils_id2abspath

	function wof_utils_find_id($id, $more=array()){

		$repo_path = wof_utils_id2repopath($id);
		$root_dirs = array(
			wof_utils_pending_dir('data'),
			$repo_path
		);
		if ($more['root_dirs']) {
			$root_dirs = $more['root_dirs'];
		}

		foreach ($root_dirs as $root) {
			$path = wof_utils_id2abspath($root, $id, $more);
			if (file_exists($path)) {
				return $path;
			}
		}

		$rsp = wof_repo_list();
		if (! $rsp['ok']) {
			return null;
		}
		$repos = $rsp['repos'];

		foreach ($repos as $repo) {
			$root = str_replace('__REPO__', $repo, $GLOBALS['cfg']['wof_data_dir']);
			$path = wof_utils_id2abspath($root, $id, $more);
			if (file_exists($path)) {
				return $path;
			}
		}

		return null; // Not found!
	}

	########################################################################

	function wof_utils_find_revision($rev, $more=array()){
		if (! preg_match('/^(\d+)-(\d+)-(\d+)-(.+)\.geojson$/', $rev, $matches)) {
			return null;
		}
		list(, $timestamp, $user_id, $wof_id, $hash) = $matches;
		$date = date('Ymd', $timestamp);
		$index_dir = wof_utils_pending_dir('index');
		$log_dir = wof_utils_pending_dir("log/$date/");
		if (file_exists("$index_dir$rev")) {
			return "$index_dir$rev";
		} else if (file_exists("$log_dir$rev")) {
			return "$log_dir$rev";
		} else {
			return null;
		}
	}

	########################################################################

	function wof_utils_id2fname($id, $more=array()){

		 # PLEASE WRITE: all the alt/display name stuff

		 return "{$id}.geojson";
	}

	########################################################################

	function wof_utils_id2tree($id){

		$tree = array();
		$tmp = $id;

		while (strlen($tmp)){

			$slice = substr($tmp, 0, 3);
			array_push($tree, $slice);

			$tmp = substr($tmp, 3);
		}

		return implode(DIRECTORY_SEPARATOR, $tree);
	}

	########################################################################

	function wof_utils_pending_dir($subdir = '', $user_id = null, $branch = null) {

		$base_dir = $GLOBALS['cfg']['wof_pending_dir'];
		if (! $branch) {
			$branch = 'master';

			if ($user_id) {
				$user = users_get_by_id($user_id);
				$branch = users_settings_get_single($user, 'branch');
			} else if ($GLOBALS['cfg']['user']) {
				$branch = users_settings_get_single($GLOBALS['cfg']['user'], 'branch');
			}
		}

		// No funny business with the branch names
		if (! preg_match('/^[a-z0-9-_]+$/i', $branch)) {
			return null;
		}

		$pending_dir = "{$base_dir}{$branch}/$subdir";

		// Make sure we have a trailing slash
		if (substr($pending_dir, -1, 1) != '/') {
			$pending_dir .= '/';
		}

		return $pending_dir;
	}

	########################################################################

	function wof_utils_id2repopath($wof_id) {

		if (! $GLOBALS['cfg']['enable_feature_multi_repo']) {
			return $GLOBALS['cfg']['wof_data_dir'];
		}

		$rsp = wof_elasticsearch_search(array(
			'query' => array(
				'term' => array(
					'wof:id' => $wof_id
				)
			)
		));
		if (! $rsp['ok'] ||
		    ! $rsp['rows'][0]) {
			return null;
		}

		$wof = $rsp['rows'][0];
		$wof_repo = $wof['wof:repo'];
		if (! $wof_repo) {
			return null;
		}
		$path_template = $GLOBALS['cfg']['wof_data_dir'];
		$repo_path = str_replace('__REPO__', $wof_repo, $path_template);
		return $repo_path;
	}

	########################################################################

	function wof_utils_pickrepo($feature) {

		$repo = null;
		$props = $feature['properties'];
		$pt = $props['wof:placetype'];

		// Huh, there is already a wof:repo property. Just use that.
		if ($props['wof:repo']) {
			return array('ok' => 1, 'repo' => $props['wof:repo']);
		}

		// These placetypes are kept separately from whosonfirst-data
		$separate_pt = array(
			'venue',
			'postalcode',
			'constituency',
		);

		// These countries are split into separate region-level repos
		$include_region = array(
			'us',
		);

		if (in_array($pt, $separate_pt)) {
			$rsp = wof_utils_getcountry($feature);
			if (! $rsp['ok']) {
				return $rsp;
			}
			$country = $rsp['country'];

			if (in_array($country, $include_region)) {
				$rsp = wof_utils_getregion($feature);
				if (! $rsp['ok']) {
					return $rsp;
				}
				$region = $rsp['region'];
				$repo = "whosonfirst-data-$pt-$country-$region";
			} else {
				$repo = "whosonfirst-data-$pt-$country";
			}
		} else {
			$repo = "whosonfirst-data";
		}

		if ($repo) {
			return array('ok' => 1, 'repo' => $repo);
		} else {
			return array('ok' => 0, 'error' => 'No repo found for feature.');
		}
	}

	########################################################################

	// Given a feature, what is its country 2-letter abbreviation?

	function wof_utils_getcountry($feature) {
		$props = $feature['properties'];
		$country = null;
		if ($props['wof:country']) {
			$country = strtolower($props['wof:country']);
		} else if ($props['iso:country']) {
			$country = strtolower($props['iso:country']);
		} else if ($props['wof:hierarchy']) {
			foreach ($props['wof:hierarchy'] as $hier) {
				$country_id = $hier['country_id'];
				$country_path = wof_utils_id2relpath($country_id);
				$country_url = $GLOBALS['cfg']['data_abs_root_url'] . $country_path;
				$more = array(
					'http_timeout' => 60
				);
				$rsp = http_get($country_url, array(), $more);
				if (! $rsp['ok']) {
					return $rsp;
				}

				$wof = json_decode($rsp['body'], 'as hash');
				if ($wof['properties']['wof:country']) {
					$country = strtolower($wof['properties']['wof:country']);
				} else if ($wof['properties']['iso:country']) {
					$country = strtolower($wof['properties']['iso:country']);
				}

				if ($country) {
					break;
				}
			}
		}
		if ($country) {
			return array('ok' => 1, 'country' => $country);
		} else {
			return array('ok' => 0, 'error' => 'No country found for feature.');
		}
	}

	########################################################################

	// Given a feature, what is its region 2-letter abbreviation?

	function wof_utils_getregion($feature) {
		$props = $feature['properties'];
		$region = null;
		if (! $props['wof:hierarchy']) {
			return array(
				'ok' => 0,
				'error' => 'We need a wof:hierarchy to determine which repo to save to.'
			);
		}
		foreach ($props['wof:hierarchy'] as $hier) {
			$region_id = $hier['region_id'];
			$region_path = wof_utils_id2relpath($region_id);
			$region_url = $GLOBALS['cfg']['data_abs_root_url'] . $region_path;
			$more = array(
				'http_timeout' => 60
			);
			$rsp = http_get($region_url, array(), $more);
			if (! $rsp['ok']) {
				return $rsp;
			}

			$wof = json_decode($rsp['body'], 'as hash');
			if ($wof['properties']['wof:abbreviation']) {
				$region = strtolower($wof['properties']['wof:abbreviation']);
			} else if ($wof['properties']['wof:subdivision']) {
				$region = substr($wof['properties']['wof:subdivision'], 3);
				$region = strtolower($region);
			} else if ($wof['properties']['iso:subdivision']) {
				$region = substr($wof['properties']['iso:subdivision'], 3);
				$region = strtolower($region);
			}

			if ($region) {
				break;
			}
		}
		if ($region) {
			return array('ok' => 1, 'region' => $region);
		} else {
			return array('ok' => 0, 'error' => 'No region found for feature.');
		}
	}

	# the end
