<?php

	$GLOBALS['users_settings_defaults'] = array(
		'branch' => 'master',
		'show_git_branch' => 0
	);

	$GLOBALS['users_settings_cache'] = array();

	########################################################################

	function users_settings_set($user, $name, $value){

		$user_id = $user['id'];

		$enc_user_id = addslashes($user_id);
		$enc_name = addslashes($name);
		$enc_value = addslashes($value);

		$rsp = db_insert_dupe('users_settings', array(
			'user_id' => $enc_user_id,
			'name'    => $enc_name,
			'value'   => $enc_value
		), array(
			'value'   => $enc_value
		));

		if (isset($GLOBALS['users_settings_cache'][$user_id])){
			unset($GLOBALS['users_settings_cache'][$user_id]);
		}

		return $rsp;
	}

	########################################################################

	function users_settings_get_single($user, $key){

		$rsp = users_settings_get($user);

		if (! $rsp['ok']){
			return null;	# maybe?
		}

		$settings = $rsp['settings'];

		return (isset($settings[$key])) ? $settings[$key] : null;
	}

	########################################################################

	function users_settings_get($user){

		$user_id = $user['id'];

		if (isset($GLOBALS['users_settings_cache'][$user_id])){
			return array('ok' => 1, 'settings' => $GLOBALS['users_settings_cache'][$user_id]);
		}

		$settings = $GLOBALS['users_settings_defaults'];

		$enc_user_id = addslashes($user['id']);
		$enc_name = addslashes($name);

		$rsp = db_fetch("
			SELECT *
			FROM users_settings
			WHERE user_id = $enc_user_id
		");

		if (! $rsp['ok']){
			return $rsp;
		} else {

			foreach ($rsp['rows'] as $row){
				$k = $row['name'];
				$v = $row['value'];
				$settings[$k] = $v;
			}
		}

		$GLOBALS['users_settings_cache'][$user_id] = $settings;

		return array('ok' => 1, 'settings' => $settings);
	}
