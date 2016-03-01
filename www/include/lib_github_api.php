<?php

	loadlib("http");

	#################################################################

	$GLOBALS['github_api_endpoint'] = 'https://api.github.com/';
	$GLOBALS['github_oauth_endpoint'] = 'https://github.com/login/oauth/';

	#################################################################

	function github_api_get_auth_url(){

		$callback = $GLOBALS['cfg']['abs_root_url'] . $GLOBALS['cfg']['github_oauth_callback'];

		$oauth_key = $GLOBALS['cfg']['github_oauth_key'];
		$oauth_redir = urlencode($callback);
		$github_scope = $GLOBALS['cfg']['github_api_scope'];
		$state = crumb_generate('github_auth');

		$url = "{$GLOBALS['github_oauth_endpoint']}authorize?client_id={$oauth_key}&redirect_uri={$oauth_redir}&scope={$github_scope}&state={$state}";
		return $url;
	}

	#################################################################

	function github_api_get_auth_token($code){

		$callback = $GLOBALS['cfg']['abs_root_url'] . $GLOBALS['cfg']['github_oauth_callback'];
		$state = crumb_generate('github_auth');

		$args = array(
			'client_id' => $GLOBALS['cfg']['github_oauth_key'],
			'client_secret' => $GLOBALS['cfg']['github_oauth_secret'],
			'code' => $code,
			'redirect_uri' => $callback,
			'state' => $state
		);

		$query = http_build_query($args);

		$url = "{$GLOBALS['github_oauth_endpoint']}access_token?{$query}";
		$rsp = http_get($url);

		if (! $rsp['ok']){
			return $rsp;
		}

		$data = array();
		parse_str($rsp['body'], $data);

		if ((! $data) || (! $data['access_token'])){

			return array(
				'ok' => 0,
				'error' => 'failed to parse response'
			);
		}

		return array(
			'ok' => 1,
			'oauth_token' => $data['access_token']
		);
	}

	#################################################################

	function github_api_call($path, $args) {
		$query = http_build_query($args);
		$more = array(
			'user_agent' => 'Mapzen Boundary Issues'
		);
		$rsp = http_get("{$GLOBALS['github_api_endpoint']}$path?$query", null, $more);
		if (! $rsp['ok']) {
			return $rsp;
		} else {
			return array(
				'ok' => 1,
				'rsp' => json_decode($rsp['body'], true)
			);
		}
	}

	#################################################################
