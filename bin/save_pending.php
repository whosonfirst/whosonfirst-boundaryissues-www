<?php

	include("init_local.php");
	loadlib("wof_save");
	loadlib("offline_tasks_gearman");
	loadlib("uuid");
	loadlib("logstash");

	$options = array(
		'verbose' => false,    // -v or --verbose
		'dry_run' => false     // -d or --dry-run
	);
	foreach ($argv as $arg) {
		if (preg_match('/^-\w*v/', $arg) || $arg == '--verbose') {
			$options['verbose'] = true;
		}
		if (preg_match('/^-\w*d/', $arg) || $arg == '--dry-run') {
			$options['dry_run'] = true;
		}
	}

	if (! $options['dry_run']) {
		$lockfile = "{$GLOBALS['cfg']['wof_pending_log_dir']}SAVE_LOCKFILE";
		if (file_exists($lockfile)) {
			die("Looks like save_pending.php might already be running.\n");
		}

		// Set a lock file so we don't run more than one save at a time
		touch($lockfile);

		$task_id = uuid_v4();
		$now = offline_tasks_microtime();
		$event = array(
			'action' => 'schedule',
			'task_id' => $task_id,
			'task' => 'save_pending',
			'data' => array(),
			'rsp' => array(
				'ok' => 1
			),
			'microtime' => $now
		);
		logstash_publish('offline_tasks', $event);
	}

	$rsp = wof_save_pending($options);

	if ($options['verbose']) {
		var_export($rsp);
		echo "\n";
	}

	if (! $options['dry_run']) {

		$now = offline_tasks_microtime();
		$event = array(
			'action' => 'execute',
			'task_id' => $task_id,
			'task' => 'save_pending',
			'data' => array(),
			'rsp' => $rsp,
			'microtime' => $now
		);
		logstash_publish('offline_tasks', $event);

		// Delete the lock file; all done!
		unlink($lockfile);
	}

	exit();
?>
