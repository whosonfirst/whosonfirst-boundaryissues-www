<?php

	# This is a function for figuring out why shit broke. It should not be
	# enabled all the time, just when you are really at a loss for wtf
	# happened and you need more granular logs. (20160803/dphiffer)

	loadlib('logstash');

	########################################################################

	function audit_trail($task, $data) {

		if (! $GLOBALS['cfg']['enable_feature_audit_trail']) {
			// Use this sparingly. Off by default.
			return;
		}

		if (isset($data['ok']) &&
		    ! $data['ok']) {
			$ok = 0;
		} else {
			$ok = 1;
		}

		$record = array(
			'ok' => $ok,
			'pid' => getmypid(),
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
