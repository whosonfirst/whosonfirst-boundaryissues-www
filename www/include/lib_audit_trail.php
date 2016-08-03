<?php

	# This is a function for figuring out why shit broke. It should not be
	# enabled all the time, just when you are really at a loss for wtf
	# happened and you need more granular logs. (20160803/dphiffer)

	loadlib('logstash');
	loadlib('offline_tasks');

	function audit_trail() {

		// Note the use of func_get_args() below (i.e., pass as many
		// arguments as you want and they'll all get logged.)

		if (! $GLOBALS['cfg']['enable_feature_audit_trail']) {
			// Use this sparingly. Off by default.
			return;
		}

		$data = array();
		$args = func_get_args();
		foreach ($args as $arg) {
			if (! is_scalar($arg)) {
				$data[] = var_export($arg, true);
			} else {
				$data[] = $arg;
			}
		}
		$data = implode("\n", $data);
		$record = array(
			'pid' => posix_getpid(),
			'data' => $data,
			'microtime' => offline_tasks_microtime()
		);
		logstash_publish('audit_trail', $record);
	}
