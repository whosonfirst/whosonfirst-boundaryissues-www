<?php

	########################################################################

	function gearman_get_client() {
		if (! $GLOBALS['gearman_client']) {
			$gearman_client = new GearmanClient();
			$server_added = $gearman_client->addServer(
				$GLOBALS['cfg']['gearman_host'],
				$GLOBALS['cfg']['gearman_port']
			);
			if (! $server_added) {
				// Couldn't connect to the server
				return null;
			}
			$GLOBALS['gearman_client'] = $gearman_client;
		}
		return $GLOBALS['gearman_client'];
	}

	########################################################################
	#
	#  Pass jobs in as an associative array, like this:
	#  gearman_get_worker(array(
	#  	'callable_name' => 'handler_callback'
	#  ));
	#
	#  Then you can send the worker background jobs:
	#  gearman_background_job('callable_name', $args);
	#

	function gearman_get_worker($jobs) {
		$gearman_worker = new GearmanWorker();
		$server_added = $gearman_worker->addServer(
			$GLOBALS['cfg']['gearman_host'],
			$GLOBALS['cfg']['gearman_port']
		);
		if (! $server_added) {
			// Couldn't connect to the server
			return null;
		}
		$gearman_worker->addOptions(GEARMAN_WORKER_GRAB_UNIQ);
		foreach ($jobs as $name => $callback) {
			$gearman_worker->addFunction($name, $callback);
		}
		return $gearman_worker;
	}

	########################################################################

	function gearman_background_job($job_name, $args = null) {

		$gearman_client = gearman_get_client();
		if (! $gearman_client) {
			return array(
				'ok' => 0,
				'error' => "Couldn't connect to the Gearman server."
			);
		}

		$args = serialize($args);
		$job_id = gearman_generate_job_id($job_name, $args);

		gearman_log("job $job_id\n$args");

		$handle = $gearman_client->doBackground($job_name, $args, $job_id);

		if ($gearman_client->returnCode() != GEARMAN_SUCCESS) {
			$code = $gearman_client->returnCode();
			$description = gearman_error_description($code);
			return array(
				'ok' => 0,
				'error' => "[$code] $description"
			);
		}

		return array(
			'ok' => 1,
			'job_id' => $job_id,
			'handle' => $handle
		);
	}

	########################################################################

	function gearman_generate_job_id($job_name, $serialized_args) {

		$user_id = 0;
		if ($GLOBALS['cfg']['user']['id']) {
			$user_id = $GLOBALS['cfg']['user']['id'];
		}

		$timestamp = time();
		$hash = md5("{$job_name}{$user_id}{$timestamp}{$serialized_args}");
		$job_id = "{$job_name}_{$user_id}_{$timestamp}_{$hash}";
		$job_id = substr($job_id, 0, 64); // GEARMAN_MAX_UNIQUE_SIZE = 64
		return $job_id;
	}

	########################################################################

	function gearman_error_description($code) {
		// TODO: add more detailed error messages
		// http://php.net/manual/en/gearmanclient.error.php
		// http://php.net/manual/en/gearmanclient.geterrno.php
		// http://php.net/manual/en/gearmanclient.returncode.php
		return "Gearman failed: $code";
	}

	########################################################################

	function gearman_log($message) {
		if (! $GLOBALS['gearman_log']) {
			$log_path = $GLOBALS['cfg']['gearman_log'];
			$GLOBALS['gearman_log'] = fopen($log_path, 'a');
			register_shutdown_function('gearman_close_log');
		}

		$date_time = date('Y-m-d H:i:s');
		fwrite($GLOBALS['gearman_log'], "[$date_time] $message\n---\n");
	}

	########################################################################

	function gearman_close_log() {
		fclose($GLOBALS['gearman_log']);
	}

	########################################################################
