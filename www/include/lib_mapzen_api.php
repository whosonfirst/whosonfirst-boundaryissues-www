<?php

	loadlib("http");

	# https://github.com/doorkeeper-gem/doorkeeper/wiki/authorization-flow
	# https://github.com/doorkeeper-gem/doorkeeper/wiki/Supported-Features
	# https://tools.ietf.org/html/draft-ietf-oauth-v2-22#section-4.1

	#################################################################

	$GLOBALS['mapzen_api_endpoint'] = 'https://mapzen.com/developers/oauth_api/';
	$GLOBALS['mapzen_oauth_endpoint'] = 'https://mapzen.com/oauth/';

	#################################################################

	function mapzen_api_get_auth_url(){

		$callback = $GLOBALS['cfg']['abs_root_url'] . $GLOBALS['cfg']['mapzen_oauth_callback'];

		$oauth_key = $GLOBALS['cfg']['mapzen_oauth_key'];

		$query = array(
			'client_id' => $oauth_key,
			'response_type' => 'code',
			'redirect_uri' => $callback,
		);

		$query = http_build_query($query);

		$url = "{$GLOBALS['mapzen_oauth_endpoint']}authorize?{$query}";
		return $url;
	}

	#################################################################

	function mapzen_api_get_auth_token($code){

		$callback = $GLOBALS['cfg']['abs_root_url'] . $GLOBALS['cfg']['mapzen_oauth_callback'];

		$query = array(
			'client_id' => $GLOBALS['cfg']['mapzen_oauth_key'],
			'client_secret' => $GLOBALS['cfg']['mapzen_oauth_secret'],
			'grant_type' => 'authorization_code',
			'redirect_uri' => $callback,
			'code' => $code,
		);

		$url = $GLOBALS['mapzen_oauth_endpoint'] . "token/";

		$headers = array();
		$more = array();

		$rsp = http_post($url, $query, $headers, $more);

		if (! $rsp['ok']){
			return $rsp;
		}

		$data = json_decode($rsp['body'], 'as hash');

		if ((! $data) || (! $data['access_token'])){
			return array("ok" => 1, "error" => "failed to parse response");
		}

		return array(
			'ok' => 1,
			'oauth_token' => $data['access_token']
		);
	}

	#################################################################

	function mapzen_api_call($endpoint, $args, $more=array()){

		$defaults = array(
			'method' => 'GET',
		);

		$more = array_merge($defaults, $more);

		if (! isset($args['access_token'])){
			return array("ok" => 0, "error" => "missing access token");
		}

		$url = $GLOBALS['mapzen_api_endpoint'] . $endpoint;

		$access_token = $args['access_token'];
		unset($args['access_token']);

		$headers = array(
			"Authorization" => "Bearer {$access_token}",
		);

		if ($more['method'] == 'GET'){

			if ($query = http_build_query($args)){
				$url = $url . "?{$query}";
			}

			$rsp = http_get($url, $headers, $more);
		}

		else {
			$rsp = array('ok' => 0, "error" => "Unsupported method type, please write me?");
		}

		if ($rsp['ok']){
			mapzen_api_parse_response($rsp);
		}

		return $rsp;
	}

	#################################################################

	function mapzen_api_parse_response(&$rsp){

		$data = json_decode($rsp['body'], 'as hash');

		if (! $data){
			$rsp["ok"] = 0;
			$rsp["error"] = "failed to parse API response";
		}

		$rsp["data"] = $data;

		# pass by ref
	}

	#################################################################

	# the end
