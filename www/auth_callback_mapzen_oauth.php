<?php

	# this is not the most elegant code - that's unfortunate but polishing
	# this particular door knob is not the priority right now and what you
	# see below is the evolution of the mapzen SSO stuff and a lot of different
	# applications trying to use it and then all playing nicely together.
	# when things settle down we will clean it up but that day is not today.
	# (20160727/thisisaaronland)

	include("include/init.php");

	loadlib("http");
	loadlib("random");
	loadlib("mapzen_api");
	loadlib("mapzen_users");

	# Some basic sanity checking like are you already logged in?

	if ($GLOBALS['cfg']['user']['id']){
		header("location: {$GLOBALS['cfg']['abs_root_url']}");
		exit();
	}


	if (! $GLOBALS['cfg']['enable_feature_signin']){
		$GLOBALS['smarty']->display("page_signin_disabled.txt");
		exit();
	}

	$code = get_str("code");

	$state_base64 = get_str("state");
	if ($state_base64){
		$state_json = base64_decode($state_base64);
		$state = json_decode($state_json, 'as hash');
	}

	if (! $code){
		error_404();
	}

	$rsp = mapzen_api_get_auth_token($code, $state_base64);

	if (! $rsp['ok']){
		$GLOBALS['error']['oauth_access_token'] = 1;
		$GLOBALS['smarty']->display("page_auth_callback_mapzen_oauth.txt");
		exit();
	}

	$oauth_token = $rsp['oauth_token'];

	$mapzen_user = mapzen_users_get_by_oauth_token($oauth_token);
	$mapzen_data = null;

	if (($mapzen_user) && ($user_id = $mapzen_user['user_id'])){

		if (! $mapzen_user['is_admin']){

			$args = array(
				'access_token' => $oauth_token,
			);

			if (features_is_enabled("mapzen_require_admin")){

				$rsp = mapzen_api_call("current_developer", $args);

				if ((! $rsp['ok']) || (! boolval($rsp['data']['admin']))){
					$GLOBALS['smarty']->display("page_signin_disabled.txt");
					exit();
				}

				$update = array('is_admin' => 1);

				$rsp = mapzen_users_update_user($mapzen_user, $update);
				$mapzen_user = $rsp['mapzen_user'];
			}
		}

	}

	# If we don't ensure that new users are allowed to create
	# an account (locally).

	else if (! $GLOBALS['cfg']['enable_feature_signup']){
		$GLOBALS['smarty']->display("page_signup_disabled.txt");
		exit();
	}

	# Hello, new user! This part will create entries in two separate
	# databases: Users and MapzenUsers that are joined by the primary
	# key on the Users table.

	else {

		$args = array(
			'access_token' => $oauth_token,
		);

		$rsp = mapzen_api_call("current_developer", $args);

		if (! $rsp['ok']){
			$GLOBALS['error']['mapzen_userinfo'] = 1;
			$GLOBALS['smarty']->display("page_auth_callback_mapzen_oauth.txt");
			exit();
		}

		$mapzen_data = $rsp['data'];
		$is_admin = boolval($mapzen_data['admin']);

		if ((features_is_enabled("mapzen_require_admin")) && (! $is_admin)){

			$GLOBALS['smarty']->display("page_signin_disabled.txt");
			exit();
		}

		# we don't get back a numeric ID yet
		# https://github.com/mapzen/operations-engineering/issues/229

		$mapzen_id = $mapzen_data['id'];
		$mapzen_user = mapzen_users_get_by_mapzen_id($mapzen_id);

		if ($mapzen_user){

			$update = array('is_admin' => $is_admin);

			$rsp = mapzen_users_update_user($mapzen_user, $update);
			$mapzen_user = $rsp['mapzen_user'];
		}
	}

	if ($mapzen_user){

		$user = users_get_by_id($mapzen_user['user_id']);

		if (! $user){
			$GLOBALS['error']['dberr_nouser'] = 1;
			$GLOBALS['smarty']->display("page_auth_callback_mapzen_oauth.txt");
			exit();
		}

		if ($mapzen_user['oauth_token'] != $oauth_token){

			$update = array('oauth_token' => $oauth_token);

			$rsp = mapzen_users_update_user($mapzen_user, $update);
			$mapzen_user = $rsp['mapzen_user'];
		}

	} else {

		$mz_id = $mapzen_data['id'];
		$username = $mapzen_data['nickname'];
		$email = $mapzen_data['email'];
		$is_admin = boolval($mapzen_data['admin']);

		# kludge... but necessary (20160727/thisisaaronland)

		$user = users_get_by_email($email);

		if (! $user){

			$password = random_string(32);

			$rsp = users_create_user(array(
				"username" => $username,
				"email" => $email,
				"password" => $password,
			));

			if (! $rsp['ok']){
				$GLOBALS['error']['dberr_user'] = 1;
				$GLOBALS['smarty']->display("page_auth_callback_mapzen_oauth.txt");
				exit();
			}

			$user = $rsp['user'];
		}

		$rsp = $mapzen_user = mapzen_users_create_user(array(
			'user_id' => $user['id'],
			'oauth_token' => $oauth_token,
			'mapzen_id' => $mz_id,
			"is_admin" => $is_admin,
		));

		if ($is_admin) {
			users_acl_grant_role($user['id'], 'staff');
		}

		$mapzen_user = $rsp['mapzen_user'];
	}

	# Okay, now finish logging the user in (setting cookies, etc.) and
	# redirecting them to some specific page if necessary.

	$redir = (isset($state['redir'])) ? urlencode($state['redir']) : '';

	login_do_login($user, $redir);
	exit();
?>
