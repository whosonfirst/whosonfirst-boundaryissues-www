<?php

	$GLOBALS['cfg']['api_log_hooks'] = 'api_log_errorlog_dispatch';

	########################################################################

	function api_log_errorlog_dispatch($data){

		$pid = getmypid();

		$data = json_encode($data);
		error_log("[API][{$pid}] {$data}");

		return array('ok' => 1);
	}

	########################################################################

	# the end