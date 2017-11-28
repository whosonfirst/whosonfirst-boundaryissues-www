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
	   repo path.

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
	recently updated files into the repo path data tree.

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
	loadlib("offline_tasks_gearman");
	loadlib("notifications");
	loadlib("wof_schema");
	loadlib("wof_events");
	loadlib("users_settings");
	loadlib("wof_repo");
	loadlib("audit_trail");

	########################################################################

	function wof_save_feature($geojson, $geometry = null, $properties = null, $collection_uuid = null, $user_id = null) {

		// THIS IS PART 3 (three) OF THE #datavoyage
		// Search the codebase for #datavoyage to follow along at home.
		// (20171121/dphiffer)
		$feature = json_decode($geojson, 'as hash');
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

		$rsp = wof_utils_pickrepo($feature);
		if (! $rsp['ok']) {
			return $rsp;
		}
		$repo = $rsp['repo'];
		$iso_country = $rsp['iso_country'];
		$wof_country = $rsp['wof_country'];

		if (! users_acl_can_edit($GLOBALS['cfg']['user'], $repo)) {
			return array(
				'ok' => 0,
				'error' => 'User is not authorized to save features.'
			);
		}

		if ($GLOBALS['cfg']['require_wof_placetypes'] &&
		    ! in_array($feature['properties']['wof:placetype'], $GLOBALS['cfg']['require_wof_placetypes'])){
			return array(
				'ok' => 0,
				'error' => 'This instance of Boundary Issues only edits ' . implode(', ', $GLOBALS['cfg']['require_wof_placetypes']) . ' placetypes.'
			);
		}

		if (is_array($properties)) {

			// Our #datavoyage is heading down the file to wof_save_merged()...
			$rsp = wof_save_merged($geojson, $geometry, $properties);
			if (! $rsp['ok']) {
				return $rsp;
			}
			$geojson = $rsp['geojson'];
			$feature = json_decode($geojson, 'as hash');
		}

		// If this is a new record, automatically copy wof:name property
		// into the name:eng_x_preferred. (20160829/dphiffer)

		if (! $feature['properties']['wof:id']) {
			$feature['properties']['name:eng_x_preferred'] = array(
				$feature['properties']['wof:name']
			);
		}

		// This next part is kind of a kludge, but necessary because when
		// PHP JSON-encodes `array()` it comes out as `[]` instead of
		// `{}`. So we just make sure to take care of wof:concordances by
		// explicity turning an empty array into an empty object.
		// (20161031/dphiffer)
		$object_props = array(
			'wof:concordances',
			'mz:hours'
		);

		foreach ($object_props as $prop) {
			if (isset($feature['properties'][$prop]) &&
			    empty($feature['properties'][$prop])) {
				$feature['properties'][$prop] = new stdClass();
			}
		}

		// Make sure the record has repo & country properties.
		// (20170726/dphiffer)
		if (! $feature['properties']['wof:repo']) {
			$feature['properties']['wof:repo'] = $repo;
		}
		if (! $feature['properties']['iso:country']) {
			$feature['properties']['iso:country'] = $iso_country;
		}
		if (! $feature['properties']['wof:country']) {
			$feature['properties']['wof:country'] = $wof_country;
		}

		// Ping Slack when someone enters a custom centroid, so that we can
		// document it properly. Note that the list of centroids is also stored
		// as a JSON spec, so this will need to be kept in sync with that.
		// (20171113/dphiffer)
		$known_centroid_prefixes = array(
			'geom',
			'intersection',
			'lbl',
			'local',
			'nav',
			'reversegeo',
			'tourist'
		);
		foreach ($feature['properties'] as $prop => $value) {
			if (preg_match('/^src:([^:]+):centroid$/', $prop, $matches)) {
				$prefix = $matches[1];
				if (! in_array($prefix, $known_centroid_prefixes)) {
					$id = $feature['properties']['wof:id'];
					$name = $feature['properties']['wof:name'];
					$url = $GLOBALS['cfg']['abs_root_url'] . "id/$id";
					slack_bot_msg(":warning: custom centroid `$prefix` used while editing <$url|$name>");
				}
			}
		}

		// Our #datavoyage now takes a moment to acknowledge the hard working
		// JSON encoders and decoders. Here in PHP-land we have made some
		// adjustments to the record prior to sending data on to the GeoJSON
		// pony. Arguably we should this logic *into* the GeoJSON pony.
		// (20171121/dphiffer)
		$geojson = json_encode($feature);

		$user = users_get_by_id($user_id);
		$branch = users_settings_get_single($user, 'branch');

		$wof_id = $feature['properties']['wof:id'];
		$orig_path = wof_utils_find_id($wof_id);

		// Validation happens on the GeoJSON service side of things
		// Our #datavoyage is heading to lib_wof_geojson.php next...
		$rsp = wof_geojson_save($geojson, $branch, $properties);

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

		$pending_path = $rsp['path'];
		if (! file_exists($pending_path)) {
			return array(
				'ok' => 0,
				'error' => 'Couldn’t find the GeoJSON output from the GeoJSON service'
			);
		}

		$changed_props = null;
		if ($orig_path && file_exists($orig_path)) {

			// Run a diff to see what changed in the pending GeoJSON.

			$changed_props = array();
			$output = array();
			exec("diff $orig_path $pending_path", $output);
			foreach ($output as $line) {
				if (preg_match('/^[<>]\s+"([^"]+)"/', $line, $matches)) {
					$prop = $matches[1];
					if (! in_array($prop, $changed_props)) {
						$changed_props[] = $prop;
					}
				}
			}
			sort($changed_props);

		}

		// At this point in the #datajourney we are doing some boring accounting
		// to ensure we can operate easily on the file later.

		$timestamp = time();
		if (! $user_id) {
			$user_id = $GLOBALS['cfg']['user']['id'];
		}

		// Look up the git hash of the pending save
		$rsp = git_execute($data_dir, "hash-object $pending_path");
		if (! $rsp['ok']) {
			return $rsp;
		}
		$git_hash = $rsp['stdout'];

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
				'url' => "/id/{$feature['properties']['wof:id']}/",
				'url_rev_arg' => "rev=$pending_index_file"
			);
			$wof_ids = array(
				$feature['properties']['wof:id']
			);
			wof_events_publish($summary, $details, $wof_ids, $user_id);
		}

		// The return #datavoyage continues...
		return array(
			'ok' => 1,
			'feature' => $feature,
			'changed_props' => $changed_props
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

		$saved_json = json_encode($saved);
		if (! $errors) {
			return array(
				'ok' => 1,
				'properties' => $batch_properties,
				'saved' => $saved_json
			);
		} else {
			return array(
				'ok' => 0,
				'properties' => $batch_properties,
				'error' => 'Error batch saving WOF documents.',
				'details' => $errors,
				'saved' => $saved_json
			);
		}
	}

	########################################################################

	// This function finds an existing WOF record and just grafts in the new
	// geometry/properties from the $geojson argument. (20160513/dphiffer)

	function wof_save_merged($geojson, $geometry, $selected_properties) {

		// THIS IS PART 4 (four) OF THE #datavoyage
		// Search the codebase for #datavoyage to follow along at home.
		// (20171121/dphiffer)
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

				if (is_numeric($prop_source)) {
					$prop_source = $prop_target;
				}

				$value = $feature['properties'][$prop_source];

				// Apply some type coersion to incoming values
				$value = wof_save_schema_value($schema, $prop_target, $value);

				if (! empty($prop_target)) {
					$existing_feature['properties'][$prop_target] = $value;
				}
			}
		}

		// Our #datavoyage is heading up the file to wof_save_feature() next...
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
		$repos = array();
		$branches = glob("{$GLOBALS['cfg']['wof_pending_dir']}*");
		foreach ($branches as $branch) {
			if (! is_dir($branch) || $branch == 'csv') {
				continue;
			}
			$branch = basename($branch);
			$rsp = wof_save_pending_branch($branch, $options);
			if ($options['verbose']) {
				echo "wof_save_pending_branch: $branch\n";
				print_r($rsp);
			}
			audit_trail("wof_save_pending_branch", $rsp, array(
				'branch' => $branch
			));
			if (! $rsp['ok']) {
				return $rsp;
			}
			$saved = array_merge($saved, $rsp['saved']);

			if ($branch == 'master') {
				$repos = array_unique($rsp['repos']);
			}
		}

		$saved_json = json_encode($saved);
		return array(
			'ok' => 1,
			'saved' => $saved_json,
			'repos' => $repos
		);
	}

	########################################################################

	function wof_save_pending_branch($branch, $options) {

		$wof = array();
		$existing = array();
		$repos = array();
		$filename_regex = '/(\d+)-(\d+)-(\d+)-(.+)\.geojson$/';
		$index_dir = wof_utils_pending_dir('index', null, $branch);

		// Group the pending updates by WOF id
		$geojson_files = glob("{$index_dir}*.geojson");
		foreach ($geojson_files as $path) {
			if (! preg_match($filename_regex, $path, $matches)) {
				continue;
			}
			list($filename, $timestamp, $user_id, $wof_id, $git_hash) = $matches;
			$repo_path = wof_utils_id2repopath($wof_id);

			if (! $repo_path) {
				$geojson = file_get_contents("$index_dir/$filename");
				$feature = json_decode($geojson, 'as hash');
				$repo = $feature['properties']['wof:repo'];
				$path_template = $GLOBALS['cfg']['wof_data_dir'];
				$repo_path = str_replace('__REPO__', $repo, $path_template);
			}

			if (! $wof[$repo_path]) {
				$wof[$repo_path] = array();
			}

			if (! $wof[$repo_path][$wof_id]) {
				$wof[$repo_path][$wof_id] = array();
				$existing_path = wof_utils_id2abspath(
					$repo_path,
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
			audit_trail("wof_save_pending_diff", $diff, array(
				'existing' => $existing[$wof_id],
				'pending' => $pending
			));

			array_push($wof[$repo_path][$wof_id], array(
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

		$repo_lookup = array();
		foreach ($wof as $repo_path => $pending) {

			if ($options['verbose']) {
				echo "----- REPO: $repo_path -----\n";
			}

			$repo_name = basename(dirname($repo_path));

			if (! file_exists($repo_path)) {
				if ($options['verbose']) {
					echo "Repo $repo_name needs to be cloned\n";
					slack_bot_msg(":warning: save_pending.php: repo $repo_name needs to be cloned");
				}
				continue;
			}

			if (! wof_repo_is_ready($repo_name)) {
				if ($options['verbose']) {
					echo "Skipping $repo_name (not ready)\n";
				}
				continue;
			}
			wof_repo_set_status($repo_name, 'saving pending', 'status updated to save pending records');

			if ($options['verbose']) {
				echo "cd $repo_path\n";
			}

			$rsp = git_branches($repo_path);
			audit_trail('git_branches', $rsp, array(
				'cwd' => $repo_path
			));
			if (! $rsp['ok']) {
				return wof_save_pending_error($repo_name, $rsp);
			}
			if ($rsp['selected'] == $branch) {
				if ($options['verbose']) {
					echo "(branch $branch already checked out)\n";
				}
			} else {
				if (! in_array($branch, $rsp['branches'])) {
					$new_branch = '-b ';
				}
				if ($options['verbose']) {
					echo "git checkout {$new_branch}$branch\n";
				}
				if (! $options['dry_run']) {
					$rsp = git_execute($repo_path, "checkout {$new_branch}$branch");
					audit_trail("git_checkout", $rsp, array(
						'cwd' => $repo_path,
						'cmd' => "git checkout {$new_branch}$branch"
					));
					if (! $rsp['ok']) {
						return wof_save_pending_error($repo_name, $rsp);
					}
				}
			}

			// Pull down changes from origin
			if ($options['verbose']) {
				echo "git pull --rebase origin $branch\n";
			}

			if (! $options['dry_run']) {
				$rsp = wof_save_pending_pull($repo_path, $branch);
				audit_trail("wof_save_pending_pull", $rsp, array(
					'cwd' => $repo_path,
					'branch' => $branch
				));
				if (! $rsp['ok']) {
					if ($options['verbose']) {
						echo "Error from wof_save_pending_pull:\n";
						print_r($rsp);
					}
					return wof_save_pending_error($repo_name, $rsp);
				}
			}

			$args = '';
			$saved = array();
			$messages = array();
			$authors = array();
			$notifications = array();

			foreach ($pending as $wof_id => $updates) {

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
					$repo_path,
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

				if (empty($update['diff'])) {
					if ($options['verbose']) {
						echo "$pending_path has no property updates! (skipping)\n";
					}
					$saved[$wof_id] = $updates;
					continue;
				}

				if ($options['verbose']) {
					echo "mv $pending_path $data_path\n";
				}
				if (! $options['dry_run']) {
					$rsp = wof_save_pending_apply_diff($pending_path, $update['diff'], $data_path, $branch);
					audit_trail('wof_save_pending_apply_diff', $rsp, array(
						'pending_path' => $pending_path,
						'diff' => $update['diff'],
						'data_path' => $data_path,
						'branch' => $branch
					));
					if (! $rsp['ok']) {
						return wof_save_pending_error($repo_name, $rsp);
					}
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
					$rsp = git_add($repo_path, $data_path);
					audit_trail('git_add', $rsp, array(
						'cwd' => $repo_path,
						'path' => $data_path
					));
					if (! $rsp['ok']) {
						return wof_save_pending_error($repo_name, $rsp);
					}
				}
				$saved[$wof_id] = $updates;
				$repo_lookup[$wof_id] = basename(dirname($repo_path));

				if ($authors[$user_id]) {
					$author = $authors[$user_id];
				} else {
					$rsp = users_get_by_id($user_id);
					audit_trail("users_get_by_id", $rsp, array(
						'id' => $user_id
					));
					if ($rsp['username']) {
						$author = $rsp['username'];
						$authors[$user_id] = $author;
					}
				}

				$message = ":round_pushpin: $wof_name ($wof_id) saved by $author";
				if ($options['verbose']) {
					echo "$message\n";
				}

				$messages[] = $message;

				if (! $notifications[$user_id]) {
					$notifications[$user_id] = array();
				}
				$notifications[$user_id][$wof_id] = $wof_name;
			}

			if (empty($messages)) {
				if ($options['verbose']) {
					echo "No pending updates for $repo_path (skipping)\n";
				}
				$repos[] = basename(dirname($repo_path));
				continue;
			}

			$num_updates = count($messages);
			$messages = implode("\n", $messages);
			$args .= ' --message=' . escapeshellarg($messages);
			$message = ":floppy_disk: Boundary Issues: $num_updates updates";

			if ($num_updates == 1) {
				$message = ":floppy_disk: Boundary Issues: saved $wof_name";
			}

			if ($options['verbose']) {
				$esc_message = escapeshellarg($message);
				echo "git commit --message=$esc_message $args\n";
			}

			if (! $options['dry_run']) {

				// Commit the pending changes
				$rsp = git_commit($repo_path, $message, $args);
				audit_trail('git_commit', $rsp, array(
					'cwd' => $repo_path,
					'message' => $message,
					'args' => $args
				));
				if (! $rsp['ok']) {
					return wof_save_pending_error($repo_name, $rsp);
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
				$rsp = git_push($repo_path, 'origin', $branch);
				audit_trail('git_push', $rsp, array(
					'cwd' => $repo_path,
					'remote' => 'origin',
					'branch' => $branch
				));
				if (! $rsp['ok']) {
					return wof_save_pending_error($repo_name, $rsp);
				}
			}

			$repos[] = basename(dirname($repo_path));
			if ($options['verbose']) {
				echo "Adding to repos list:\n";
				print_r($repos);
			}
		}

		// Clean up the pending index files

		if ($options['verbose']) {
			echo "----- CLEANING UP -----\n";
		}

		$updated = array();

		foreach ($saved as $wof_id => $updates) {
			if ($options['verbose']) {
				echo "Cleaning up $wof_id (" . count($updates) . " updates)\n";
			}
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
			$data_path = wof_utils_pending_dir("data/") .
			             wof_utils_id2relpath($wof_id);
			if ($options['verbose']) {
				echo "rm $data_path\n";
			}
			if (! $options['dry_run']) {
				unlink($data_path);
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
				$wof_id = $wof_ids[0];
				$repo = $repo_lookup[$wof_id];
				$url = "https://github.com/$owner/$repo/commit/$commit_hash";
				$payload['commit_hash'] = $commit_hash;
				$payload['url'] = $url;
			}
			notifications_publish($payload);
		}

		if ($options['verbose']) {
			echo "Resetting repos to 'ready' state\n";
		}

		foreach ($repos as $repo) {
			wof_repo_set_status($repo, 'ready', 'status updated after saving pending');
		}

		if ($options['verbose']) {
			echo "----- ALL DONE -----\n";
		}

		return array(
			'ok' => 1,
			'saved' => $updated,
			'repos' => $repos
		);
	}

	########################################################################

	function wof_save_pending_error($repo_name, $rsp) {
		if ($rsp['error']) {
			$error = $rsp['error'];
		} else {
			$error = 'Something went wrong, but I don’t know what';
		}
		$debug = "save_pending.php ($repo_name): $error";
		slack_bot_msg(":warning: $debug");
		wof_repo_set_status($repo_name, 'save pending error', $debug, $rsp);
		return $rsp;
	}

	########################################################################

	// Returns a list of properties that changed.

	function wof_save_pending_diff($existing, $pending) {

		$diff = array();

		// If this is a brand new file, we don't have any existing
		// properties to compare against.
		if (! $existing ||
		    ! $existing['properties']) {
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
				echo "Looks like $key was removed\n";
				$diff[] = $key;
			}
		}
		return $diff;
	}

	########################################################################

	function wof_save_pending_diff_value($existing, $pending) {

		if (is_scalar($existing) &&
		    is_scalar($pending)) {
			if (is_numeric($existing) && is_numeric($pending) &&
			    empty($existing) && empty($pending)) {
				// Ok, this is mostly to stop all these awful
				// commits that just change geom:area from 0.0
				// to 0. It's the same thing! Zero is zero.
				// Eventually we will track down the cause and
				// fix it, until then: no more commits!
				// (20170509/dphiffer)
				return false;
			}
			return ($existing !== $pending);
		} else if (is_scalar($existing) != is_scalar($pending)) {
			return true;
		} else if (is_array($existing)) {
			$diff = false;
			foreach ($existing as $key => $existing_value) {
				$pending_value = $pending[$key];
				$diff = $diff || wof_save_pending_diff_value($existing_value, $pending_value);
			}
			foreach ($pending as $key => $pending_value) {
				$existing_value = $existing[$key];
				$diff = $diff || wof_save_pending_diff_value($existing_value, $pending_value);
			}
			return $diff;
		}
		return true;
	}

	########################################################################

	function wof_save_pending_pull($repo_path, $branch) {
		// Pull changes from GitHub
		$rsp = git_pull($repo_path, 'origin', $branch, '--rebase');
		audit_trail("git_pull", $rsp, array(
			'cwd' => $GLOBALS['cfg']['wof_data_dir'],
			'remote' => 'origin',
			'branch' => $branch,
			'args' => '--rebase'
		));
		if (! $rsp['ok'] &&
		    strpos($rsp['stdout'], "Couldn\\'t find remote ref $branch") === false) {
			// We are okay with the "branch doesn't exist yet on GitHub" error
			return $rsp;
		}

		// Index updated records in Elasticsearch
		if ($rsp['commit_hashes']) {
			$commit_hashes = $rsp['commit_hashes'];
			$commit_hashes_esc = escapeshellarg($commit_hashes);
			$rsp = git_execute($repo_path, "diff $commit_hashes_esc --summary");
			audit_trail("summarize_commit_hashes", $rsp, array(
				'cwd' => $repo_path,
				'cmd' => "git $commit_hashes_esc --summary"
			));
			if (! $rsp['ok']) {
				return array(
					'ok' => 0,
					'error' => "Could not determine changed files from $commit_hashes_esc."
				);
			}
			preg_match_all('/(\d+)\.geojson/', $rsp['stdout'], $matches);
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
			$repo = basename($repo_path);
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

	function wof_save_pending_apply_diff($pending_path, $diff, $existing_path, $branch = 'master') {

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

		$ref = 'https://whosonfirst.mapzen.com/schema/whosonfirst.schema#';
		$schema = wof_schema_fields($ref);
		// Read this next line in Steve Balmer's voice—
		$props = $schema['properties']['properties']['properties'];

		// Massage the datatypes from the JSON schema
		foreach ($existing['properties'] as $key => $value) {
			if (! $props[$key]) {
				continue;
			}
			if ($props[$key]['type'] == 'integer') {
				$existing['properties'][$key] = intval($value);
			} else if ($props[$key]['type'] == 'number') {
				$existing['properties'][$key] = floatval($value);
			} else if ($props[$key]['type'] == 'object') {
				$existing['properties'][$key] = (object) $value;
			} else if ($props[$key]['type'] == 'string') {
				$existing['properties'][$key] = "$value";
			}
		}

		$geojson = json_encode($existing);
		$rsp = wof_geojson_encode($geojson, $branch);
		audit_trail('wof_geojson_encode', $rsp, array(
			'geojson' => $geojson,
			'branch' => $branch
		));
		if (! $rsp['ok']) {
			return $rsp;
		}

		file_put_contents($existing_path, $rsp['encoded']);
		return array('ok' => 1);
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
