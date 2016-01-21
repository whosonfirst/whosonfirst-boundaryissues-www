<?php

	loadlib("logstash");

	$GLOBALS['api_log_hooks']['dispatch'] = 'api_log_logstash_dispatch';

	########################################################################

	function api_log_logstash_dispatch($data){

		$rsp = logstash_publish('api', $data);
		return $rsp;
	}

	########################################################################

	# the end