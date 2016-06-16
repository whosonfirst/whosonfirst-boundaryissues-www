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
	{$WOF_ID}-{$UNIX_EPOCH}-{$USER_ID}-{$GIT_HASH}.geojson

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

	*/

	########################################################################

	$GLOBALS['find_path'] = '/usr/bin/find';

	########################################################################

	loadlib('wof_utils');
	loadlib('wof_geojson');
	loadlib('wof_elasticsearch');
	loadlib('artisanal_integers');
	loadlib("git");
	loadlib("github_api");
	loadlib("github_users");
	loadlib("offline_tasks_gearman");
	loadlib("notifications");

	########################################################################

	function wof_save_feature($geojson, $geometry = null, $properties = null, $collection_uuid = null) {

		$feature = json_decode($geojson, true);
		if (! $feature) {
			return array(
				'ok' => 0,
				'error' => "Could not parse input 'geojson' param."
			);
		}

		if (is_array($properties)) {
			$rsp = wof_save_merged($geojson, $geometry, $properties);
			if (! $rsp) {
				return $rsp;
			}
			$geojson = $rsp['geojson'];
		}

		// Validation happens on the GeoJSON service side of things
		$rsp = wof_geojson_save($geojson);

		if (! $rsp['ok']) {
			$rsp['error'] = "Error saving via GeoJSON service: {$rsp['error']}";
			return $rsp;
		}

		$geojson = $rsp['geojson'];
		$feature = json_decode($geojson, true);
		if (! $feature) {
			return array(
				'ok' => 0,
				'error' => 'GeoJSON was saved to disk, but it seems to be unparseable.'
			);
		}

		$wof_id = $feature['properties']['wof:id'];
		$timestamp = time();
		$user_id = $GLOBALS['cfg']['user']['id'];
		$data_dir = "{$GLOBALS['cfg']['wof_pending_dir']}data/";

		$pending_path = wof_utils_id2abspath($data_dir, $wof_id);

		// Look up the git hash of the pending save
		$rsp = git_execute($data_dir, "hash-object $pending_path");
		if (! $rsp['ok']) {
			return $rsp;
		}
		$git_hash = $rsp['rsp'];

		// Save a snapshot to the pending/log directory
		$pending_index_dir = "{$GLOBALS['cfg']['wof_pending_dir']}index/";
		if (! file_exists($pending_index_dir)) {
			mkdir($pending_index_dir, 0775, true);
		}
		$pending_index_file = "$wof_id-$timestamp-$user_id-$git_hash.geojson";
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

		// Something something $collection_uuid...

		return array(
			'ok' => 1,
			'wof_id' => $wof_id,
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
		if (! file_exists($existing_geojson_path)) {
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

		// Update selected properties
		if ($selected_properties) {
			foreach ($selected_properties as $prop_source => $prop_target) {
				$value = $feature['properties'][$prop_source];
				$existing_feature['properties'][$prop_target] = $value;
			}
		}

		return array(
			'ok' => 1,
			'geojson' => json_encode($existing_feature)
		);
	}

	########################################################################

	function wof_save_to_github($wof_id, $oauth_token = null) {

		// The GitHub API doesn't always like us, so fall back on
		// plain vanilla `git` if the feature flag hasn't been enabled.
		if (! $GLOBALS['cfg']['enable_feature_save_via_github_api']) {
			return wof_save_with_git($wof_id, $oauth_token);
		}

		if (! $oauth_token) {
			// Get the GitHub oauth token if none was specified
			$rsp = github_users_curr_oauth_token();
			if (! $rsp['ok']) {
				return $rsp;
			}
			$oauth_token = $rsp['oauth_token'];
		}

		$data_dir = "{$GLOBALS['cfg']['wof_pending_dir']}data/";

		$rel_path = wof_utils_id2relpath($wof_id);
		$abs_path = wof_utils_id2abspath($data_dir, $wof_id);

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

	########################################################################

	function wof_save_with_git($wof_id, $oauth_token = null) {

		// Designed to be interchangable, interface-wise, with the
		// wof_save_to_github function. Basically GitHub's API started
		// consistently returning 500 errors, so I made this workaround
		// that uses vanilla `git` calls. (20160429/dphiffer)

		$data_dir = "{$GLOBALS['cfg']['wof_pending_dir']}data/";

		$rel_path = wof_utils_id2relpath($wof_id);
		$abs_path = wof_utils_id2abspath($data_dir, $wof_id);

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
		$author = "{$rsp['info']['name']} ({$rsp['info']['login']})";
		$esc_author = escapeshellarg($author);

		$rsp = git_add($GLOBALS['cfg']['wof_data_dir'], $abs_path);
		if (! $rsp) {
			return $rsp;
		}

		$args = "--author=$esc_author";
		$rsp = git_commit($GLOBALS['cfg']['wof_data_dir'], $message, $args);
		if (! $rsp) {
			return $rsp;
		}

		$rsp = git_push($GLOBALS['cfg']['wof_data_dir']);
		if (! $rsp) {
			return $rsp;
		}

		return array(
			'ok' => 1,
			'saved' => $rel_path,
			'output' => $rsp['output']
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

		if ($options['verbose']) {
			echo "git pull --rebase origin master\n";
		}

		if (! $options['dry_run']) {
			// Pull changes from GitHub
			$rsp = git_pull($GLOBALS['cfg']['wof_data_dir'], 'origin', 'master', '--rebase');
			if (! $rsp['ok']) {
				return array(
					'ok' => 0,
					'error' => "Problem with git pull: {$rsp['error']}{$rsp['output']}"
				);
			}

			// Index updated records in Elasticsearch
			if ($rsp['commit_hashes']) {
				$commit_hashes_esc = escapeshellarg($rsp['commit_hashes']);
				$rsp = git_execute($GLOBALS['cfg']['wof_data_dir'], "diff $commit_hashes_esc --summary");
				if ($rsp['ok']) {
					$output = "{$rsp['error']}{$rsp['output']}";
					preg_match_all('/(\d+)\.geojson/', $output, $matches);
					foreach ($matches[1] as $wof_id) {

						// Load GeoJSON record data
						$path = wof_utils_id2abspath($GLOBALS['cfg']['wof_data_dir'], $wof_id);
						$geojson = file_get_contents($path);
						$feature = json_decode($geojson, 'as hash');

						if ($options['verbose']) {
							echo "schedule index: $wof_id\n";
						}

						// Schedule an offline index
						$rsp = offline_tasks_schedule_task('index', array(
							'geojson_data' => $feature
						));
					}
				}
			}
		}

		$wof = array();
		$filename_regex = '/(\d+)-(\d+)-(\d+)-(.+)\.geojson$/';
		$index_dir = "{$GLOBALS['cfg']['wof_pending_dir']}index/";
		$date = date('Ymd');
		$log_dir = "{$GLOBALS['cfg']['wof_pending_dir']}/log/$date/";

		// Group the pending updates by WOF id
		$files = glob("{$index_dir}*.geojson");
		foreach ($files as $file) {
			if (! preg_match($filename_regex, $file, $matches)) {
				continue;
			}
			list($filename, $wof_id, $timestamp, $user_id, $git_hash) = $matches;
			if (! $wof[$wof_id]) {
				$wof[$wof_id] = array();
			}
			array_push($wof[$wof_id], array(
				'wof_id' => intval($wof_id),
				'filename' => $filename,
				'timestamp' => intval($timestamp),
				'user_id' => intval($user_id),
				'git_hash' => $git_hash
			));
		}

		if (empty($wof)) {
			return array(
				'ok' => 1,
				'saved' => array()
			);
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
				"{$GLOBALS['cfg']['wof_pending_dir']}data/",
				$wof_id
			);

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
				echo "cp $pending_path $data_path\n";
			}
			if (! $options['dry_run']) {
				copy($pending_path, $data_path);
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

			$geojson = file_get_contents($pending_path);
			$feature = json_decode($geojson, 'as hash');
			$wof_name = $feature['properties']['wof:name'];
			$user_id = $update['user_id'];

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
			array_push($notifications[$user_id], $wof_name);
		}

		$messages = implode("\n", $messages);
		$args .= ' --message=' . escapeshellarg($messages);
		$num_updates = count($saved);
		$message = "Boundary Issues: $num_updates updates";

		if ($num_updates == 1) {
			$message = "Boundary Issues: 1 update";
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
		}

		if ($options['verbose']) {
			echo "git push origin master\n";
		}

		if (! $options['dry_run']) {

			// Push to GitHub
			$rsp = git_push($GLOBALS['cfg']['wof_data_dir'], 'origin', 'master');
			if (! $rsp['ok']) {
				return $rsp;
			}
		}

		// Clean up the pending index files
		$updated = array();

		if (! file_exists($log_dir)) {
			mkdir($log_dir, 0775, true);
		}

		foreach ($saved as $wof_id => $updates) {
			foreach ($updates as $update) {
				$filename = $update['filename'];
				if ($options['verbose']) {
					echo "mv $index_dir$filename $log_dir$filename\n";
				}
				if (! $options['dry_run']) {
					rename("$index_dir$filename", "$log_dir$filename");
				}
			}
			$pending_path = wof_utils_id2abspath(
				"{$GLOBALS['cfg']['wof_pending_dir']}data/",
				$wof_id
			);
			if ($options['verbose']) {
				echo "rm $pending_path\n";
			}
			if (! $options['dry_run']) {
				unlink($pending_path);
			}
			$updated[] = $updates[0];
		}

		// Clean up any empty data directories
		$find_path = $GLOBALS['find_path'];
		$pending_dir = realpath("{$GLOBALS['cfg']['wof_pending_dir']}data/");
		if ($options['verbose']) {
			echo "find $pending_dir -type d -empty -delete\n";
		}
		if (! $options['dry_run']) {
			exec("$find_path $pending_dir -type d -empty -delete");
		}

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

		// Send out notifications
		foreach ($notifications as $user_id => $wof_updates) {
			$count = count($wof_updates);
			if ($count == 1) {
				$title = "Published {$wof_updates[0]}";
				$body = 'Your updates are on GitHub now.';
			} else {
				$title = "Published $count updates";
				$body = implode(', ', $wof_updates);
			}
			$payload = array(
				'title' => $title,
				'body' => $body,
				'user_ids' => array($user_id)
			);
			notifications_publish($payload);
		}

		return array(
			'ok' => 1,
			'updated' => $updated
		);
	}

	########################################################################

	function wof_save_feature_collection($path, $geometry, $properties, $collection_uuid) {

		$geojson = file_get_contents($path);
		$collection = json_decode($geojson, 'as hash');
		$errors = array();

		foreach ($collection['features'] as $index => $feature) {
			$geojson = json_encode($feature);
			$rsp = offline_tasks_schedule_task('process_feature', array(
				'geojson' => $geojson,
				'geometry' => $geometry,
				'properties' => $properties,
				'collection_uuid' => $collection_uuid
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
			return array(
				'ok' => 1,
				'collection_uuid' => $collection_uuid
			);
		}
	}

	# the end
