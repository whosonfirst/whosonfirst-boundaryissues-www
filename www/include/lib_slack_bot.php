<?php

	loadlib('http');

	$GLOBALS['cfg']['slack_bot_users_list'] = dirname(dirname(__DIR__)) . "/www/meta/slack_bot_users_list.json";

	########################################################################

	function slack_bot_msg($msg, $extras = array()){

		if ($extras['url']) {
			$url = $extras['url'];
		} else {
			$url = $GLOBALS['cfg']['slack_bot_webhook_url'];
		}

		if (! $GLOBALS['cfg']['enable_feature_slack_bot']){
			return array(
				'ok' => 0,
				'error' => 'enable_feature_slack_bot not enabled'
			);
		} else if ($webhook_url == 'READ-FROM-SECRETS'){
			return array(
				'ok' => 0,
				'error' => 'Configure slack_bot_webhook_url'
			);
		}

		$user_regex = '/@([a-zA-Z0-9_]+)/i';
		$msg = preg_replace_callback($user_regex, function($matches){
			$id = slack_bot_user_id($matches[1]);
			if ($id) {
				return "<@$id>";
			} else {
				return $matches[0];
			}
		}, $msg);

		$message = array(
			'text' => $msg
		);
		if ($extras['attachments']) {
			$message['attachments'] = $extras['attachments'];
		}

		$postfields = json_encode($message);
		$headers = array(
			'Content-Type: application/json'
		);
		$rsp = http_post($url, $postfields, $headers);

		return $rsp;
	}

	########################################################################

	function slack_bot_user_id($username) {

		// This exists because Slack notifies users by their user ID
		// rather than their visible username.
		// See: https://api.slack.com/docs/message-formatting#linking_to_channels_and_users
		// (20170728/dphiffer)

		$rsp = slack_bot_users_list();
		if (! $rsp['ok']) {
			return null;
		}

		$users = $rsp['users_list'];
		if ($users[$username]) {
			return $users[$username];
		} else {
			return null;
		}
	}

	########################################################################

	function slack_bot_users_list(){

		$cache_file = $GLOBALS['cfg']['slack_bot_users_list'];
		if (file_exists($cache_file)){
			$json = file_get_contents($cache_file);
			$users = json_decode($json, 'as hash');
			return array(
				'ok' => 1,
				'users_list' => $users
			);
		}

		return slack_bot_load_users_list();
	}

	########################################################################

	function slack_bot_load_users_list(){

		$access_token = $GLOBALS['cfg']['slack_bot_access_token'];
		if ($access_token == 'READ-FROM-SECRETS'){
			return array(
				'ok' => 0,
				'error' => "Please configure 'slack_bot_access_token'"
			);
		}

		$rsp = http_post('https://slack.com/api/users.list', array(
			'token' => $access_token,
			'presence' => false
		));
		if (! $rsp['ok']){
			return $rsp;
		}

		$list = json_decode($rsp['body'], 'as hash');
		$users = array();

		foreach ($list['members'] as $user){
			$name = $user['name'];
			$users[$name] = $user['id'];
		}

		return array(
			'ok' => 1,
			'users_list' => $users
		);
	}

	# the end
