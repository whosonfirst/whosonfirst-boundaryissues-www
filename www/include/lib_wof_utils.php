<?php

	loadlib('users_settings');
	loadlib('wof_elasticsearch');

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
		dbug("repo_path = $repo_path");
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

	# the end
