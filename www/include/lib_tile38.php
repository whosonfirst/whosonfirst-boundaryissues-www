<?php

	loadlib("http");

	########################################################################

	function tile38_do($cmd, $more=array()){

		$host = $more['host'];
		$port = $more['port'];

		$url = "http://{$host}:{$port}";

		$headers = array();

		$rsp = http_post($url, $cmd, $headers, $more);

		if (! $rsp['ok']){
			return $rsp;
		}

		$body = json_decode($rsp['body'], 'as hash');

		if (! $body){
			return array('ok' => 0, 'error' => 'failed to parse response');
		}

		return $body;
	}

	########################################################################

	# the end