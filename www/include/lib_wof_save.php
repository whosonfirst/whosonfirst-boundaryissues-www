<?php

	/*

	How saving works.
	=================

	Note: each of the backtick commands below are running from the
	git_execute() command from lib_git.php.

	Step one: save a pending WOF record.
	------------------------------------
	When you hit the 'save' button in Boundary Issues, an AJAX request
	sends off the document to the WOF GeoJSON Service, which in turn saves
	a record to {$DIR}/pending (where {$DIR} is the Boundary Issues base
	dir). The pending directory uses the same folder structure as other
	WOF data sources.

	Additionally, a snapshot of the file is added to the {$DIR}/pending/log
	subdirectory. The filename of that copy has the following syntax:
	{$UNIX_EPOCH}-{$USER_ID}-{$WOF_ID}-{$GIT_HASH}.geojson

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

	Step six: send the updates to Amazon S3.
	----------------------------------------
	Each file also gets beamed up to The Cloud so all the things stay in
	sync.

	Note: this used to work differently, first by making calls to the GitHub
	      API and then by creating git commits upon hitting save. You can
	      find the old code at: 1c0bd7a5e33d133741c7496c2239cabfbc05a2c3
	      (20160711/dphiffer)

	*/

	########################################################################

	$GLOBALS['find_path'] = '/usr/bin/find';

	########################################################################

	loadlib("wof_utils");
	loadlib("wof_geojson");
	loadlib("git");
	loadlib("github_api");
	loadlib("github_users");
	loadlib("offline_tasks_gearman");
	loadlib("notifications");
	loadlib("wof_schema");
	loadlib("wof_events");
	loadlib("users_settings");

	########################################################################

	function wof_save_feature($geojson, $geometry = null, $properties = null, $collection_uuid = null, $user_id = null) {

		$feature = json_decode($geojson, true);
		if (! $feature) {
			return array(
				'ok' => 0,
				'error' => "Could not parse input 'geojson' param."
			);
		}

		if (! $user_id &&
		    ! $GLOBALS['cfg']['user']) {
			return array(
				'ok' => 0,
				'error' => 'Cannot save feature without a user.'
			);
		} else if ($GLOBALS['cfg']['user']) {
			$user_id = $GLOBALS['cfg']['user']['id'];
		}

		if (is_array($properties)) {
			$rsp = wof_save_merged($geojson, $geometry, $properties);
			if (! $rsp['ok']) {
				return $rsp;
			}
			$geojson = $rsp['geojson'];
		}

		$user = users_get_by_id($user_id);
		$branch = users_settings_get_single($user, 'branch');

		// Validation happens on the GeoJSON service side of things
		$rsp = wof_geojson_save($geojson, $branch);

		if (! $rsp['ok']) {
			$rsp['error'] = "Error saving via GeoJSON service: {$rsp['error']}";
			return $rsp;
		}

		$geojson = $rsp['geojson'];
		$feature = json_decode($geojson, 'as hash');
		if (! $feature) {
			return array(
				'ok' => 0,
				'error' => 'GeoJSON was saved to disk, but it seems to be unparseable.'
			);
		}

		$wof_id = $feature['properties']['wof:id'];
		$timestamp = time();
		if (! $user_id) {
			$user_id = $GLOBALS['cfg']['user']['id'];
		}
		$data_dir = wof_utils_pending_dir('data', $user_id);

		$pending_path = wof_utils_id2abspath($data_dir, $wof_id);

		// Look up the git hash of the pending save
		$rsp = git_execute($data_dir, "hash-object $pending_path");
		if (! $rsp['ok']) {
			return $rsp;
		}
		$git_hash = $rsp['rsp'];

		// Save a snapshot to the pending index directory
		$pending_index_dir = wof_utils_pending_dir('index', $user_id);
		if (! file_exists($pending_index_dir)) {
			mkdir($pending_index_dir, 0775, true);
		}
		$pending_index_file = "$timestamp-$user_id-$wof_id-$git_hash.geojson";
		$pending_index_path = "$pending_index_dir$pending_index_file";
		file_put_contents($pending_index_path, $geojson);

		// Make sure the pending log file actually exists
		if (! file_exists($pending_index_path)) {
			return array(
				'ok' => 0,
				'error' => "Oh no, your pending change wasn't saved."
			);
		}

		// Schedule an offline index of the new record
		$rsp = offline_tasks_schedule_task('index', array(
			'feature' => $feature
		));
		if (! $rsp['ok']) {
			return $rsp;
		}

		if ($collection_uuid) {
			notifications_publish(array(
				'collection_uuid' => $collection_uuid,
				'wof_id' => $feature['properties']['wof:id'],
				'wof_name' => $feature['properties']['wof:name']
			));
		} else {
			$summary = "Saved {$feature['properties']['wof:name']}";
			$details = array(
				'filename' => $pending_index_file,
				'url' => "/id/{$feature['properties']['wof:id']}/?rev=$pending_index_file"
			);
			$wof_ids = array(
				$feature['properties']['wof:id']
			);
			wof_events_publish($summary, $details, $wof_ids, $user_id);
		}

		return array(
			'ok' => 1,
			'feature' => $feature
		);
	}

	########################################################################

	function wof_save_batch($batch_ids, $batch_properties) {
		$errors = array();
		$saved = array();
		foreach ($batch_ids as $wof_id) {
			$geojson_path = wof_utils_find_id($wof_id);
			if ($geojson_path) {
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
					$saved[] = $rsp['feature'];
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

	########################################################################

	// This function finds an existing WOF record and just grafts in the new
	// geometry/properties from the $geojson argument. (20160513/dphiffer)

	function wof_save_merged($geojson, $geometry, $selected_properties) {

		$feature = json_decode($geojson, 'as hash');
		if (! $feature) {
			return array(
				'ok' => 0,
				'error' => 'Could not parse GeoJSON.'
			);
		}

		$props = $feature['properties'];
		if (! $props) {
			// Just in case, check if there's a top-level 'id' param
			if (! $feature['id']) {
				return array(
					'ok' => 0,
					'error' => 'No GeoJSON properties found (we just need a wof:id).'
				);
			}
		}

		if ($props['wof:id']) {
			$wof_id = intval($props['wof:id']);
		} else if ($props['wof_id']) {
			// ogr2ogr seems to not like colons in property names
			$wof_id = intval($props['wof_id']);
		} else if ($props['id']) {
			// ogr2ogr seems to not like colons in property names
			$wof_id = intval($props['id']);
		} else if ($feature['id']) {
			$wof_id = intval($feature['id']);
		} else {
			return array(
				'ok' => 0,
				'error' => 'No wof:id (or wof_id) property found.'
			);
		}

		$existing_geojson_path = wof_utils_find_id($wof_id);
		if (! $existing_geojson_path ||
		    ! file_exists($existing_geojson_path)) {
			return array(
				'ok' => 0,
				'error' => "wof:id $wof_id not found."
			);
		}

		$existing_geojson = file_get_contents($existing_geojson_path);
		$existing_feature = json_decode($existing_geojson, 'as hash');

		// Update the geometry
		if ($geometry) {
			$existing_feature['geometry'] = $feature['geometry'];
		}

		$ref = 'https://whosonfirst.mapzen.com/schema/whosonfirst.schema#';
		$schema = wof_schema_fields($ref);

		// Update selected properties
		if ($selected_properties) {
			foreach ($selected_properties as $prop_source => $prop_target) {
				$value = $feature['properties'][$prop_source];

				// Apply some type coersion to incoming values
				$value = wof_save_schema_value($schema, $prop_target, $value);

				$existing_feature['properties'][$prop_target] = $value;
			}
		}

		return array(
			'ok' => 1,
			'geojson' => json_encode($existing_feature)
		);
	}

	########################################################################

	function wof_save_pending($options) {

		$defaults = array(
			'verbose' => false,
			'dry_run' => false
		);
		$options = array_merge($defaults, $options);

		if ($options['verbose']) {
			echo "wof_save_pending\n";
			echo "----------------\n";
			var_export($options);
			echo "\n";
		}

		$saved = array();
		$branches = glob("{$GLOBALS['cfg']['wof_pending_dir']}*");
		foreach ($branches as $branch) {
			if (! is_dir($branch)) {
				continue;
			}
			$branch = basename($branch);
			$rsp = wof_save_pending_branch($branch, $options);
			if (! $rsp['ok']) {
				return $rsp;
			}
			$saved = array_merge($saved, $rsp['saved']);
		}

		return array(
			'ok' => 1,
			'saved' => $saved
		);
	}

	########################################################################

	function wof_save_pending_branch($branch, $options) {

		$wof = array();
		$existing = array();
		$filename_regex = '/(\d+)-(\d+)-(\d+)-(.+)\.geojson$/';
		$index_dir = wof_utils_pending_dir('index', null, $branch);

		// Group the pending updates by WOF id
		$geojson_files = glob("{$index_dir}*.geojson");
		foreach ($geojson_files as $path) {
			if (! preg_match($filename_regex, $path, $matches)) {
				continue;
			}
			list($filename, $timestamp, $user_id, $wof_id, $git_hash) = $matches;
			if (! $wof[$wof_id]) {
				$wof[$wof_id] = array();
				$existing_path = wof_utils_id2abspath(
					$GLOBALS['cfg']['wof_data_dir'],
					$wof_id
				);
				if (file_exists($existing_path)) {
					$existing_geojson = file_get_contents($existing_path);
					$existing[$wof_id] = json_decode($existing_geojson, 'as hash');
				} else {
					$existing[$wof_id] = null;
				}
			}

			// Figure out which properties changed
			$pending_geojson = file_get_contents($path);
			$pending = json_decode($pending_geojson, 'as hash');
			$diff = wof_save_pending_diff($existing[$wof_id], $pending);

			array_push($wof[$wof_id], array(
				'path' => $path,
				'wof_id' => intval($wof_id),
				'filename' => $filename,
				'timestamp' => intval($timestamp),
				'user_id' => intval($user_id),
				'git_hash' => $git_hash,
				'diff' => $diff
			));
		}

		if (empty($wof)) {
			return array(
				'ok' => 1,
				'saved' => array()
			);
		}

		$rsp = git_branches($GLOBALS['cfg']['wof_data_dir']);
		if (! $rsp['ok']) {
			return $rsp;
		}

		if (! in_array($branch, $rsp['branches'])) {
			$new_branch = '-b ';
		}

		if ($options['verbose']) {
			echo "git checkout {$new_branch}$branch\n";
		}
		if (! $options['dry_run']) {
			$rsp = git_execute($GLOBALS['cfg']['wof_data_dir'], "checkout {$new_branch}$branch");
		}

		// Pull down changes from origin
		if ($options['verbose']) {
			echo "git pull --rebase origin $branch\n";
		}

		if (! $options['dry_run']) {
			$rsp = wof_save_pending_pull($branch);
			if (! $rsp['ok']) {
				return $rsp;
			}
		}

		$args = '';
		$saved = array();
		$messages = array();
		$authors = array();
		$notifications = array();

		foreach ($wof as $wof_id => $updates) {

			// Find the most recent pending changes
			usort($updates, function($a, $b) {
				if ($a['timestamp'] < $b['timestamp']) {
					return 1;
				} else {
					return -1;
				}
			});
			$update = $updates[0];

			$data_path = wof_utils_id2abspath(
				$GLOBALS['cfg']['wof_data_dir'],
				$wof_id
			);
			$pending_path = wof_utils_id2abspath(
				wof_utils_pending_dir('data', null, $branch),
				$wof_id
			);

			$geojson = file_get_contents($pending_path);
			$feature = json_decode($geojson, 'as hash');
			$wof_name = $feature['properties']['wof:name'];
			$user_id = $update['user_id'];

			$data_dir = dirname($data_path);
			if (! file_exists($data_dir)) {
				if ($options['verbose']) {
					echo "mkdir -p $data_dir\n";
				}
				if (! $options['dry_run']) {
					mkdir($data_dir, 0775, true);
				}
			}

			if ($options['verbose']) {
				echo "mv $pending_path $data_path\n";
			}
			if (! $options['dry_run']) {
				save_pending_apply_diff($pending_path, $update['diff'], $data_path, $branch);
				//rename($pending_path, $data_path);
			}

			if (! file_exists($data_path) &&
			    ! $options['dry_run']) {
				if ($options['verbose']) {
					echo "not found: $data_path\n";
				}
				continue;
			}


			if ($options['verbose']) {
				echo "git add $data_path\n";
			}

			if (! $options['dry_run']) {
				git_add($GLOBALS['cfg']['wof_data_dir'], $data_path);
			}
			$saved[$wof_id] = $updates;

			if ($authors[$user_id]) {
				$author = $authors[$user_id];
			} else {
				$rsp = github_users_get_author_by_user_id($user_id);
				if ($rsp['ok']) {
					$author = $rsp['author'];
					$authors[$user_id] = $author;
				}
			}
			$messages[] = "* $wof_name ($wof_id) saved by $author";

			if (! $notifications[$user_id]) {
				$notifications[$user_id] = array();
			}
			$notifications[$user_id][$wof_id] = $wof_name;
		}

		$messages = implode("\n", $messages);
		$args .= ' --message=' . escapeshellarg($messages);
		$num_updates = count($saved);
		$message = "Boundary Issues: $num_updates updates";

		if ($num_updates == 1) {
			$message = "Boundary Issues: saved $wof_name";
		}

		if ($options['verbose']) {
			echo "git commit \"$message\" $args\n";
		}

		if (! $options['dry_run']) {

			// Commit the pending changes
			$rsp = git_commit($GLOBALS['cfg']['wof_data_dir'], $message, $args);
			if (! $rsp['ok']) {
				return $rsp;
			}
			if (preg_match('/^\[\w+\s+(.+?)\]/', $rsp['rsp'], $matches)) {
				$commit_hash = $matches[1];
			}
		}

		if ($options['verbose']) {
			echo "git push origin $branch\n";
		}

		if (! $options['dry_run']) {

			// Push to GitHub
			$rsp = git_push($GLOBALS['cfg']['wof_data_dir'], 'origin', $branch);
			if (! $rsp['ok']) {
				return $rsp;
			}
		}

		// Clean up the pending index files
		$updated = array();

		foreach ($saved as $wof_id => $updates) {
			foreach ($updates as $update) {
				if ($options['verbose']) {
					$date = date('Ymd');
					$log_dir = wof_utils_pending_dir("log/$date/");
					echo "mv {$update['path']} $log_dir$filename\n";
				}
				if (! $options['dry_run']) {
					wof_save_log($update['path']);
				}
			}
			$updated[] = $updates[0];
		}

		// Clean up any empty data directories
		$find_path = $GLOBALS['find_path'];
		$pending_dir = realpath(wof_utils_pending_dir('', null, $branch));
		if ($options['verbose']) {
			echo "find $pending_dir -type d -empty -delete\n";
		}
		if (! $options['dry_run']) {
			exec("$find_path $pending_dir -type d -empty -delete");
		}

		if ($branch == 'master') {
			// Schedule S3 updates
			foreach ($saved as $wof_id => $updates) {

				if ($options['verbose']) {
					echo "schedule update_s3: $wof_id\n";
				}

				if (! $options['dry_run']) {
					$rsp = offline_tasks_schedule_task('update_s3', array(
						'wof_id' => $wof_id
					));
					if ($options['verbose']) {
						var_export($rsp);
					}
				}
			}
		}

		// Send out notifications
		foreach ($notifications as $user_id => $wof_updates) {
			$count = count($wof_updates);
			$wof_ids = array_keys($wof_updates);
			$wof_names = array_values($wof_updates);
			if ($count == 1) {
				$title = "Published {$wof_names[0]} to GitHub";
				$body = ":sparkles::floppy_disk::sparkles:";
			} else if ($count > 5) {
				$title = "Published $count updates to GitHub";
				$wof_updates = array_slice($wof_names, 0, 5);
				$body = implode(', ', $wof_names) . '...';
			} else {
				$title = "Published $count updates to GitHub";
				$body = implode(', ', $wof_names);
			}
			$payload = array(
				'title' => $title,
				'body' => $body,
				'user_ids' => array($user_id),
				'wof_ids' => $wof_ids
			);
			if ($commit_hash) {
				$owner = $GLOBALS['cfg']['wof_github_owner'];
				$repo = $GLOBALS['cfg']['wof_github_repo'];
				$url = "https://github.com/$owner/$repo/commit/$commit_hash";
				$payload['commit_hash'] = $commit_hash;
				$payload['url'] = $url;
			}
			notifications_publish($payload);
		}

		return array(
			'ok' => 1,
			'saved' => $updated
		);
	}

	########################################################################

	// Returns a list of properties that changed.

	function wof_save_pending_diff($existing, $pending) {

		$diff = array();

		// If this is a brand new file, we don't have any existing
		// properties to compare against.
		if (! $existing) {
			// ... so the diff is easy: all of the properties.
			return array_keys($pending);
		}

		// Check for property updates
		foreach ($pending['properties'] as $key => $pending_value) {
			$existing_value = $existing['properties'][$key];
			if (wof_save_pending_diff_value($existing_value, $pending_value)) {
				$diff[] = $key;
			}
		}

		// Check for property deletions
		foreach ($existing['properties'] as $key => $existing_value) {
			if (! isset($pending['properties'][$key])) {
				$diff[] = $key;
			}
		}
		return $diff;
	}

	########################################################################

	function wof_save_pending_diff_value($existing, $pending) {

		if (is_scalar($existing) &&
		    is_scalar($pending)) {
			return ($existing === $pending);
		} else if (is_scalar($existing) != is_scalar($pending)) {
			return true;
		} else if (is_array($existing)) {
			$diff = false;
			foreach ($existing as $key => $existing_value) {
				$pending_value = $pending[$key];
				$diff = $diff || wof_save_pending_diff_value($existing_value, $pending_value);
			}
			return $diff;
		}
		return true;
	}

	########################################################################

	function wof_save_pending_pull($branch) {
		// Pull changes from GitHub
		$rsp = git_pull($GLOBALS['cfg']['wof_data_dir'], 'origin', $branch, '--rebase');
		if (! $rsp['ok'] &&
		    strpos($rsp['rsp'], "Couldn\\'t find remote ref $branch") !== false) {
			// We are okay with the "branch doesn't exist yet on GitHub" error
			return array(
				'ok' => 0,
				'error' => "Problem with git pull: {$rsp['error']}{$rsp['output']}"
			);
		}

		// Index updated records in Elasticsearch
		if ($rsp['commit_hashes']) {
			$commit_hashes = $rsp['commit_hashes'];
			$commit_hashes_esc = escapeshellarg($commit_hashes);
			$rsp = git_execute($GLOBALS['cfg']['wof_data_dir'], "diff $commit_hashes_esc --summary");
			if (! $rsp['ok']) {
				return array(
					'ok' => 0,
					'error' => "Could not determine changed files from $commit_hashes_esc."
				);
			}
			$output = "{$rsp['error']}{$rsp['output']}";
			preg_match_all('/(\d+)\.geojson/', $output, $matches);
			$wof_ids = array_map('intval', $matches[1]);
			foreach ($wof_ids as $wof_id) {

				// Load GeoJSON record data
				$path = wof_utils_id2abspath($GLOBALS['cfg']['wof_data_dir'], $wof_id);
				$geojson = file_get_contents($path);
				$feature = json_decode($geojson, 'as hash');

				// Schedule an offline index
				$rsp = offline_tasks_schedule_task('index', array(
					'geojson_data' => $feature
				));
			}

			$owner = $GLOBALS['cfg']['wof_github_owner'];
			$repo = $GLOBALS['cfg']['wof_github_repo'];
			$range = str_replace('..', '...', $commit_hashes);
			$url = "https://github.com/$owner/$repo/compare/$range";
			$details = array(
				'commit_hashes' => $commit_hashes,
				'wof_ids' => $wof_ids,
				'url' => $url
			);
			$count = count($wof_ids);
			$s = ($count == 1) ? '' : 's';
			wof_events_publish("Updated {$count} record{$s} from git pull", $details, $wof_ids);
		}

		return array(
			'ok' => 1
		);
	}

	########################################################################

	function save_pending_apply_diff($pending_path, $diff, $existing_path, $branch = 'master') {

		// This function takes two paths, and merges each of the
		// properties listed in $diff from the $pending_path GeoJSON
		// into the one at $existing_path.

		// If the file doesn't exist already, just copy over the
		// $pending_path file.
		if (! file_exists($existing_path)) {
			copy($pending_path, $existing_path);
			return array('ok' => 1);
		}

		$pending_json = file_get_contents($pending_path);
		$pending = json_decode($pending_json, 'as hash');

		$existing_json = file_get_contents($existing_path);
		$existing = json_decode($existing_json, 'as hash');

		foreach ($diff as $key) {
			if (isset($pending['properties'][$key])) {
				$existing['properties'][$key] = $pending['properties'][$key];
			} else {
				unset($existing['properties'][$key]);
			}
			if ($key == 'wof:geomhash') {
				$existing['geometry'] = $pending['geometry'];
			}
		}

		$geojson = json_encode($existing);
		$rsp = wof_geojson_save($geojson, $branch);
		return $rsp;
	}

	########################################################################

	function wof_save_feature_collection($path, $geometry, $properties, $collection_uuid, $user_id) {

		$geojson = file_get_contents($path);
		$collection = json_decode($geojson, 'as hash');
		$errors = array();

		foreach ($collection['features'] as $index => $feature) {
			$geojson = json_encode($feature);
			$rsp = offline_tasks_schedule_task('process_feature', array(
				'geojson' => $geojson,
				'geometry' => $geometry,
				'properties' => $properties,
				'collection_uuid' => $collection_uuid,
				'user_id' => $user_id
			));
			if (! $rsp['ok']) {
				$errors[] = "Scheduling feature $index: {$rsp['error']}";
			}
		}

		if ($errors) {
			if (count($errors) > 5) {
				// Don't go into detail about more than 5 errors
				$errors = array_slice($errors, 0, 5);
			}
			$error = implode(', ', $errors);
			return array(
				'ok' => 0,
				'error' => $error,
				'collection_uuid' => $collection_uuid
			);
		} else {

			// Announce how many features we are processing
			notifications_publish(array(
				'collection_uuid' => $collection_uuid,
				'feature_count' => count($collection['features'])
			));

			// Move the FeatureCollection geojson to the log dir
			wof_save_log($path);

			return array(
				'ok' => 1,
				'collection_uuid' => $collection_uuid
			);
		}
	}

	########################################################################

	# Move a pending geojson file into the log directory, organized by date.

	function wof_save_log($path) {
		$date = date('Ymd');
		$log_dir = wof_utils_pending_dir("log/$date/");
		if (! file_exists($log_dir)) {
			mkdir($log_dir, 0775, true);
		}
		$filename = basename($path);
		rename($path, "$log_dir$filename");
	}

	########################################################################

	# Apply type coersion based on JSON schema

	function wof_save_schema_value($schema, $prop, $value) {

		// Read this next line in the voice of an excited Steve Balmer
		$props = $schema['properties']['properties']['properties'];

		if (! $props[$prop]['type']) {
			return $value;
		} else if ($props[$prop]['type'] == 'integer') {
			return intval($value);
		} else if ($props[$prop]['type'] == 'number') {
			return floatval($value);
		} else if ($props[$prop]['type'] == 'string') {
			return "$value";
		} else {
			return $value;
		}
	}

	# the end
