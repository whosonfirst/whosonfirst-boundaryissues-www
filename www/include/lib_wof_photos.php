<?php

	loadlib("http");
	
	########################################################################

	function wof_photos_get($wof_id){

		$data = array(
			'access_token' => $GLOBALS['cfg']['wof_photos_access_token'],
			'wof_id' => $wof_id
		);
		$url = $GLOBALS['cfg']['wof_photos_url'];
		$rsp = http_post("$url/api/rest/?method=wof.photos_get", $data);
		if (! $rsp['ok']){
			return $rsp;
		}
		$body = json_decode($rsp['body'], 'as hash');
		$photos = $body['photos'];

		return array(
			'ok' => 1,
			'url' => $url,
			'photos' => $photos
		);
	}

	# the end
