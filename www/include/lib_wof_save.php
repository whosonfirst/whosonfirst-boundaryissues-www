<?php

	/*

	How saving works.
	=================

	Note: each of the backtick commands below are running from the
	git_execute() command from lib_git.php.

	Another note: this is not describing how things _currently actually_
	work. Rather, this is describing how saving things _will_ work, very
	soon. (20160502/dphiffer)

	Step one: save a pending WOF record.
	------------------------------------
	When you hit the 'save' button in Boundary Issues, an AJAX request
	sends off the document to the WOF GeoJSON Service, which in turn saves
	a copy of the file in {$DIR}/pending (where {$DIR} is the Boundary
	Issues base dir).

	The pending filename will have the following syntax:
	{$WOF_ID}-{$UNIX_EPOCH}-{$GIT_HASH}.geojson

	This means at any given time, if you want to read the current state of
	the WOF data, it will require two steps:

	1. Check {$DIR}/pending for the latest record (if any).
	2. If a pending record is *not* found, find the record from the
	   $GLOBALS['cfg']['wof_data_dir'] repo.

	Step two: pull changes from GitHub.
	-----------------------------------
	From here onward, these steps are assumed to be invoked as an offline
	task, perhaps on a crontab. We're also assuming a "clean" repo, with no
	pending changes in the data directory to interfere with `git pull`.

	This step effectively does the following:
	`git pull --rebase origin master`

	Basically this will just make sure the most recent updates from GitHub
	get pulled in before we commit on top of them.

	Step three: add pending WOF updates.
	------------------------------------
	For each file in {$DIR}/pending, determine the most recent update for a
	given WOF ID (using the Unix timestamp in the filename). Copy the most
	recently updated files into the $GLOBALS['cfg']['wof_data_dir'] data
	tree.

	For each file copied, stage the file for a git commit:
	`git add {$PATH_TO_UPDATED_WOF}`

	Step four: commit and push.
	---------------------------
	Finally, we are ready to commit the changes and push to GitHub.

	`git commit -m "Boundary Issues saved {$LIST_OF_FILES}"`
	`git push origin master`

	Step five: clean up pending files.
	----------------------------------
	For each of the files for a given saved WOF ID, remove all timestamp
	versions that are older than the version added in step three. Leave any
	pending files that are *newer* than the GeoJSON timestamp that was
	updated for a future save process.

	Thing that are likely to fail in practice.
	------------------------------------------
	* There may be conflicts with `git pull --rebase origin master`.
	* We may get rejected when attempting `git push origin master`.
	* All updated files from step two will need to be reindexed by
	  Elasticsearch.

	*/

	loadlib('wof_utils');
	loadlib('wof_geojson');
	loadlib('wof_elasticsearch');
	loadlib('artisanal_integers');
	loadlib("git");
	loadlib("github_api");
	loadlib("github_users");
	loadlib("offline_tasks");
	loadlib("offline_tasks_gearman");

	function wof_save_file($input_path) {

		$filename = basename($input_path);

		if (! file_exists($input_path)){
			return array(
				'ok' => 0,
				'error' => "$filename not found."
			);
		}

		$geojson = file_get_contents($input_path);
		$rsp = wof_save_feature($geojson);

		// Clean up the uploaded tmp file
		if (is_uploaded_file($input_path)) {
			unlink($input_path);
		}

		// It worked \o/
		return $rsp;

	}

	function wof_save_feature($geojson) {

		$geojson_data = json_decode($geojson, true);
		if (! $geojson_data) {
			return array(
				'ok' => 0,
				'error' => "Could not parse input 'geojson' param."
			);
		}

		// Validation happens on the GeoJSON service side of things
		$rsp = wof_geojson_save($geojson);

		if (! $rsp['ok']) {
			$rsp['error'] = "Error saving via GeoJSON service: {$rsp['error']}";
			return $rsp;
		}

		$geojson = $rsp['geojson'];
		$geojson_data = json_decode($geojson, true);
		if (! $geojson_data) {
			return array(
				'ok' => 0,
				'error' => 'GeoJSON was saved to disk, but it seems to be unparseable.'
			);
		}

		$wof_id = $geojson_data['properties']['wof:id'];

		$rsp = offline_tasks_schedule_task('commit', array(
			'wof_id' => $wof_id,
			'user_id' => $GLOBALS['cfg']['user']['id']
		));
		if (! $rsp['ok']) {
			return $rsp;
		}

		$rsp = offline_tasks_schedule_task('index', array(
			'geojson_data' => $geojson_data
		));
		if (! $rsp['ok']) {
			return $rsp;
		}

		return array(
			'ok' => 1,
			'wof_id' => $wof_id,
			'geojson' => $geojson_data
		);
	}

	function wof_save_batch($batch_ids, $batch_properties) {
		$errors = array();
		$saved = array();
		foreach ($batch_ids as $wof_id) {
			$geojson_path = wof_utils_id2abspath($GLOBALS['cfg']['wof_data_dir'], $wof_id);
			if (file_exists($geojson_path)) {
				$existing_geojson = file_get_contents($geojson_path);
				$existing_feature = json_decode($existing_geojson, true);
				$existing_feature['properties'] = array_merge(
					$existing_feature['properties'],
					$batch_properties
				);
				$updated_geojson = json_encode($existing_feature);
				$rsp = wof_save_feature($updated_geojson);

				if (! $rsp['ok']) {
					$errors[$wof_id] = $rsp['error'];
				} else {
					$saved[] = $rsp['geojson'];
				}
			} else {
				$errors[$wof_id] = "Could not find WOF GeoJSON file.";
			}
		}

		if (! $errors) {
			return array(
				'ok' => 1,
				'properties' => $batch_properties,
				'saved' => $saved
			);
		} else {
			return array(
				'ok' => 0,
				'properties' => $batch_properties,
				'error' => 'Error batch saving WOF documents.',
				'details' => $errors,
				'saved' => $saved
			);
		}
	}

	function wof_save_to_github($wof_id, $oauth_token = null) {

		// The GitHub API doesn't always like us, so fall back on
		// plain vanilla `git` if the feature flag hasn't been enabled.
		if (! $GLOBALS['cfg']['enable_feature_save_via_github_api']) {
			return wof_save_with_git($data['wof_id'], $oauth_token);
		}

		if (! $oauth_token) {
			// Get the GitHub oauth token if none was specified
			$rsp = github_users_curr_oauth_token();
			if (! $rsp['ok']) {
				return $rsp;
			}
			$oauth_token = $rsp['oauth_token'];
		}

		$rel_path = wof_utils_id2relpath($wof_id);
		$abs_path = wof_utils_id2abspath(
			$GLOBALS['cfg']['wof_data_dir'],
			$wof_id
		);

		$geojson_str = file_get_contents($abs_path);
		$feature = json_decode($geojson_str, "as hash");
		$wof_name = $feature['properties']['wof:name'];
		$filename = basename($rel_path);

		$owner = $GLOBALS['cfg']['wof_github_owner'];
		$repo = $GLOBALS['cfg']['wof_github_repo'];
		$github_path = "repos/$owner/$repo/contents/data/$rel_path";

		$args = array(
			'path' => "data/$rel_path",
			'content' => base64_encode($geojson_str)
		);

		// If the file exists, find its SHA hash
		$rsp = github_api_call('GET', $github_path, $oauth_token);
		if ($rsp['ok']) {
			$what_happened = 'updated';
			$args['sha'] = $rsp['rsp']['sha'];
		} else {
			$what_happened = 'created';
		}

		$args['message'] = "Boundary Issues $what_happened $filename ($wof_name)";

		// Save to GitHub
		$rsp = github_api_call('PUT', $github_path, $oauth_token, $args);
		if (! $rsp['ok']) {
			$rsp['error'] = "HTTP PUT to '$github_path': {$rsp['error']}";
			return $rsp;
		}

		return array(
			'ok' => 1,
			'url' => $rsp['rsp']['content']['_links']['html']
		);
	}

	function wof_save_with_git($wof_id, $oauth_token = null) {

		// Designed to be interchangable, interface-wise, with the
		// wof_save_to_github function. Basically GitHub's API started
		// consistently returning 500 errors, so I made this workaround
		// that uses vanilla `git` calls. (20160429/dphiffer)

		$rel_path = wof_utils_id2relpath($wof_id);
		$abs_path = wof_utils_id2abspath(
			$GLOBALS['cfg']['wof_data_dir'],
			$wof_id
		);

		$geojson_str = file_get_contents($abs_path);
		$feature = json_decode($geojson_str, "as hash");
		$wof_name = $feature['properties']['wof:name'];
		$filename = basename($rel_path);
		$message = "Boundary Issues saved $filename ($wof_name)";

		// Retrieve the name/email address from GitHub to sign the
		// commit message with.

		$rsp = github_users_info($oauth_token);
		if (! $rsp) {
			return $rsp;
		}
		$author = "{$rsp['info']['name']} <{$rsp['info']['email']}>";

		$rsp = git_add($abs_path);
		if (! $rsp) {
			return $rsp;
		}

		$rsp = git_commit($message, $author);
		if (! $rsp) {
			return $rsp;
		}

		$rsp = git_push();
		if (! $rsp) {
			return $rsp;
		}

		return array(
			'ok' => 1,
			'saved' => $rel_path,
			'output' => $rsp['output']
		);
	}
