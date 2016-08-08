<?php

	# This is a function for figuring out why shit broke. It should not be
	# enabled all the time, just when you are really at a loss for wtf
	# happened and you need more granular logs. (20160803/dphiffer)

	loadlib('logstash');

	########################################################################

	function audit_trail($task, $rsp, $args = null) {

		if (! $GLOBALS['cfg']['enable_feature_audit_trail']) {
			// Use this sparingly. Off by default.
			return;
		}

		if (isset($rsp['ok']) &&
		    ! $rsp['ok']) {
			$ok = 0;
		} else {
			$ok = 1;
		}

		$data = array();
		if ($args) {
			$data['args'] = $args;
		}
		$data['rsp'] = $rsp;

		// Elasticsearch cannot be trusted with arbitrary structured data, because ... mappings?
		$data = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

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
