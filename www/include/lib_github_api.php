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

	function github_api_call($method, $path, $args) {
		$more = array(
			// See: https://developer.github.com/v3/#user-agent-required
			'user_agent' => 'Mapzen Boundary Issues'
		);
		
		$headers = array(
			'Content-Type' => 'application/json',
			'Accept' => 'application/vnd.github.v3+json'
		);
		
		if ($args['oauth_token']) {
			$headers['Authorization'] = "token {$args['oauth_token']}";
		}
		
		$data = json_encode($args);
		if ($method == 'GET') {
			$query = http_build_query($args);
			$rsp = http_get("{$GLOBALS['github_api_endpoint']}$path?$query", null, $more);
		} else if ($method == 'POST') {
			$rsp = http_post("{$GLOBALS['github_api_endpoint']}$path", $data, $headers, $more);
		} else if ($method == 'PUT') {
			$rsp = http_put("{$GLOBALS['github_api_endpoint']}$path", $data, $headers, $more);
		} else if ($method == 'DELETE') {
			$rsp = http_delete("{$GLOBALS['github_api_endpoint']}$path", $data, $headers, $more);
		}
		
		if (! $rsp['ok']) {
			$rsp['github_api_call'] = array(
				'url' => "{$GLOBALS['github_api_endpoint']}$path",
				'data' => $data,
				'headers' => $headers,
				'more' => $more
			);
			return $rsp;
		} else {
			return array(
				'ok' => 1,
				'rsp' => json_decode($rsp['body'], true)
			);
		}
	}

	#################################################################
