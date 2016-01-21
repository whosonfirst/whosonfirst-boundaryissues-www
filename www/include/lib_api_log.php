<?php

	$GLOBALS['api_log'] = array();

	$GLOBALS['api_log_hooks'] = array(
		'dispatch' => null,
	);

	########################################################################

	function api_log($data, $dispatch=0){

		$GLOBALS['api_log'] = array_merge($GLOBALS['api_log'], $data);

		if (! $dispatch){
			return array('ok' => 1);
		}

		$func = $GLOBALS['api_log_hooks']['dispatch'];

		if ((! $func) || (! function_exists($func))){
			return array('ok' => 0, 'error' => 'Dispatch function has not been declared');
		}

		$rsp = call_user_func($func, $GLOBALS['api_log']);

		$GLOBALS['api_log'] = array();

		return $rsp;
	}

	########################################################################

	# A simple helper function to gather and scrub API parameters for
	# passing along to the logging facilities (20140616/straup)

	function api_log_request_params(){

		$params = $_REQUEST;
		unset($params['access_token']);
		unset($params['method']);

		return $params;
	}

	########################################################################

	# the end
