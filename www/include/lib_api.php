<?php

 	#################################################################

	# HEY LOOK! RUNNING CODE!!!

	$start = microtime_ms();

	loadlib("api_config");
	api_config_init();

	$end = microtime_ms();
	$time = $end - $start;

	$GLOBALS['timing_keys']["api_init"] = "API init";
	$GLOBALS['timings']['api_init_count'] += 1;
	$GLOBALS['timings']['api_init_time'] += $time;

 	#################################################################

	loadlib("api_output");
	loadlib("api_log");

	loadlib("api_auth");
	loadlib("api_keys");
	loadlib("api_keys_utils");
	loadlib("api_throttle");
	loadlib("api_utils");

	#################################################################

	function api_dispatch($method){

		if (! $GLOBALS['cfg']['enable_feature_api']){
			api_output_error(999, 'API disabled');
		}

		# Necessary? Also causes PHP 5.5 to freak out
		# with older versions of lib_filter...
		# (20140122/straup)

		$method = filter_strict($method);

		$api_key = request_str("api_key");
		$access_token = request_str("access_token");

		# Log the basics

		$addr = remote_addr();

		$params = api_log_request_params();

		api_log(array(
			'method' => $method,
			'params' => $params,
			'access_token_hash' => sha1($access_token),
			'remote_addr' => $addr,
		));

		apache_setenv("API_METHOD", $method);

		$methods = $GLOBALS['cfg']['api']['methods'];

		if ((! $method) || (! isset($methods[$method]))){
			$enc_method = htmlspecialchars($method);
			api_output_error(404, "Method '{$enc_method}' not found");
		}


		$method_row = $methods[$method];

		$key_row = null;
		$token_row = null;

		if (! $method_row['enabled']){
			$enc_method = htmlspecialchars($method);
			api_output_error(404, "Method '{$enc_method}' not found");
		}

		$method_row['name'] = $method;

		if ($GLOBALS['cfg']['api_auth_type'] == 'oauth2'){

			if (($_SERVER['REQUEST_METHOD'] != 'POST') && (! $GLOBALS['cfg']['api_oauth2_allow_get_parameters'])){
			 	api_output_error(405, 'Method not allowed');
			}
		}

		if (isset($method_row['request_method'])){

			$allowed = $method_row['request_method'];

			if (! is_array($allowed)){
				$allowed = array($allowed);
			}

			if (! in_array($_SERVER['REQUEST_METHOD'], $allowed)){
				api_output_error(405, 'Method not allowed');
			}

			
		}

		# Okay – now we get in to validation and authorization. Which means a
		# whole world of pedantic stupid if we're using Oauth2. Note that you
		# could use OAuth2 and require API keys be passed explictly but since
		# that's not part of the spec if you enable the two features simultaneously
		# don't be surprised when hilarity ensues. Good times. (20121026/straup)

		# First API keys
 
		if (features_is_enabled("api_require_keys")){

			if (! $api_key){
				api_output_error(999, "Required API key is missing");
			}

			$key_row = api_keys_get_by_key($api_key);
			api_keys_utils_ensure_valid_key($key_row);

			api_log(array('api_key_id' => $key_row['id']));
		}

		# Second auth-y bits

		$auth_rsp = api_auth_ensure_auth($method_row, $key_row);

		if (isset($auth_rsp['api_key'])){
			$key_row = $auth_rsp['api_key'];
			api_log(array('api_key_id' => $key_row['id']));
		}

		if (isset($auth_rsp['access_token'])){
			$token_row = $auth_rsp['access_token'];
			api_log(array('auth_token_id' => $token_row['id']));
		}
	
		if ($auth_rsp['user']){
			$GLOBALS['cfg']['user'] = $auth_rsp['user'];
			api_log(array('user_id' => $auth_rsp['user']['id']));
		}

		apache_setenv("API_KEY", $key_row['api_key']);

		# Check for require-iness of users here ?

		# Roles - for API keys (things like only the site keys)

		api_config_ensure_roles($method_row, $key_row, $token_row);

		# Blessings and other method specific access controls

		api_config_ensure_blessing($method_row, $key_row, $token_row);

		# Finally, crumbs - because they are tastey

		if ($method_row['requires_crumb']){
			api_auth_ensure_crumb($method_row);
		}

		# GO!

		loadlib($method_row['library']);

		$parts = explode(".", $method);
		$method = array_pop($parts);

		$func = "{$method_row['library']}_{$method}";

		if (! function_exists($func)){
			api_output_error(404, "Method not found");
		}

		call_user_func($func);
		exit();
	}

	#################################################################

	# the end
