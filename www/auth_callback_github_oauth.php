<?php

	include("include/init.php");

	loadlib("http");
	loadlib("random");
	loadlib("github_api");
	loadlib("github_users");

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

	if (! $code){
		error_404();
	}

	$rsp = github_api_get_auth_token($code);

	if (! $rsp['ok']){
		$GLOBALS['error']['oauth_access_token'] = 1;
		$GLOBALS['smarty']->display("page_auth_callback_github_oauth.txt");
		exit();
	}

	$oauth_token = $rsp['oauth_token'];

	$rsp = github_api_call('GET', "user", $oauth_token);

	if (! $rsp['ok']){
		$GLOBALS['error']['github_userinfo'] = 1;
		$GLOBALS['smarty']->display("page_auth_callback_github_oauth.txt");
		exit();
	}

	$github_id = $rsp['rsp']['id'];
	$github_user = github_users_get_by_github_id($github_id);

	if ($github_user){

		$user_id = $github_user['user_id'];

		if (! $user_id){
			$GLOBALS['error']['github_missing_userid'] = 1;
			$GLOBALS['smarty']->display("page_auth_callback_github_oauth.txt");
			exit();
		}

		$user = users_get_by_id($user_id);

		if (! $user){
			$GLOBALS['error']['github_missing_user'] = 1;
			$GLOBALS['smarty']->display("page_auth_callback_github_oauth.txt");
			exit();
		}
		
		if ($user['deleted']){
			$GLOBALS['error']['github_deleted_user'] = 1;
			$GLOBALS['smarty']->display("page_auth_callback_github_oauth.txt");
			exit();
		}

		if ($github_user['oauth_token'] != $oauth_token){

			$rsp = github_users_update_user($github_user, array(
				'oauth_token' => $oauth_token
			));

			if (! $rsp['ok']){
				$GLOBALS['error']['github_token_update'] = 1;
				$GLOBALS['smarty']->display("page_auth_callback_github_oauth.txt");
				exit();
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
	# databases: Users and GithubUsers that are joined by the primary
	# key on the Users table.

	else {
		$rsp = github_api_call('GET', "user", $oauth_token);

		if (! $rsp['ok']){
			$GLOBALS['error']['github_userinfo'] = 1;
			$GLOBALS['smarty']->display("page_auth_callback_github_oauth.txt");
			exit();
		}

		$github_id = $rsp['rsp']['id'];
		$username = $rsp['rsp']['name'];

		$rsp = github_api_call('GET', 'user/emails', $oauth_token);

		if (! $rsp['ok']){
			$GLOBALS['error']['github_userinfo'] = 1;
			$GLOBALS['smarty']->display("page_auth_callback_github_oauth.txt");
			exit();
		}

		$email = $rsp['rsp'][0]['email'];

		if (! $email){
			$email = "{$github_id}@donotsend-github.com";
		}

		$password = random_string(32);

		$rsp = users_create_user(array(
			"username" => $username,
			"email" => $email,
			"password" => $password,
		));

		if (! $rsp['ok']){
			$GLOBALS['error']['dberr_user'] = 1;
			$GLOBALS['smarty']->display("page_auth_callback_github_oauth.txt");
			exit();
		}

		$user = $rsp['user'];

		if (! $user){
			$GLOBALS['error']['dberr_user'] = 1;
			$GLOBALS['smarty']->display("page_auth_callback_github_oauth.txt");
			exit();
		}

		$github_user = github_users_create_user(array(
			'user_id' => $user['id'],
			'oauth_token' => $oauth_token,
			'github_id' => $github_id,
		));

		if (! $github_user){
			$GLOBALS['error']['dberr_githubuser'] = 1;
			$GLOBALS['smarty']->display("page_auth_callback_github_oauth.txt");
			exit();
		}
	}

	# Okay, now finish logging the user in (setting cookies, etc.) and
	# redirecting them to some specific page if necessary.

	$redir = (isset($extra['redir'])) ? $extra['redir'] : '';

	login_do_login($user, $redir);
	exit();
?>
