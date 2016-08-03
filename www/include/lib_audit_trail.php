<?php

	# This is a function for figuring out why shit broke. It should not be
	# enabled all the time, just when you are really at a loss for wtf
	# happened and you need more granular logs. (20160803/dphiffer)

	loadlib('logstash');

	########################################################################

	function audit_trail($task, $data) {

		// Note the use of func_get_args() below (i.e., pass as many
		// arguments as you want and they'll all get logged.)

		if (! $GLOBALS['cfg']['enable_feature_audit_trail']) {
			// Use this sparingly. Off by default.
			return;
		}

		$record = array(
			'pid' => posix_getpid(),
			'task' => $task,
			'data' => $data,
			'microtime' => audit_trail_microtime()
		);
		$rsp = logstash_publish('audit_trail', $record);

		return $rsp;
	}

	########################################################################

	function audit_trail_microtime(){

		list($usec, $sec) = explode(" ", microtime());
		return (float)$sec + (float)$usec;
	}

	########################################################################

	# the end
