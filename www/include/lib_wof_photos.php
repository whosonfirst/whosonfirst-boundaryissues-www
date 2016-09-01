<?php

	loadlib("flickr_api");

	########################################################################

	function wof_photos_flickr_search($woe_id){
		$rsp = flickr_api_call('flickr.photos.search', array(
			'api_key' => $GLOBALS['cfg']['flickr_api_key'],
			'woe_id' => $woe_id,
			'safe_search' => 2,
			'license' => '4,5,6,7,8'
		));
		if (! $rsp['ok']){
			return $rsp;
		}

		return array(
			'ok' => 1,
			'photos' => $rsp['rsp']['photos']['photo']
		);
	}

	########################################################################

	function wof_photos_flickr_src($photo, $size = 'z'){
		extract($photo);
		return "https://farm{$farm}.staticflickr.com/{$server}/{$id}_{$secret}_{$size}.jpg";
	}
	
	########################################################################
	
	function wof_photos_assign_flickr_photo($wof_id, $flickr_id){

		if (! $GLOBALS['cfg']['user']['id'] ||
		    ! $wof_id ||
		    ! $flickr_id){
			return array(
				'ok' => 0,
				'error' => "You must be logged in, and you must provide a wof_id and flickr_id."
			);
		}

		$rsp = flickr_api_call("flickr.photos.getInfo", array(
			'api_key' => $GLOBALS['cfg']['flickr_api_key'],
			'photo_id' => $flickr_id
		));
		if (! $rsp['ok']){
			return array(
				'ok' => 0,
				'error' => "Could not load getInfo from Flickr."
			);
		}

		$esc_wof_id = intval($wof_id);
		$esc_user_id = intval($GLOBALS['cfg']['user']['id']);
		$info = $rsp['rsp']['photo'];
		$info_json = json_encode($info);

		$rsp = db_write("
			DELETE FROM boundaryissues_photos
			WHERE wof_id = $esc_wof_id
		");
		if (! $rsp['ok']){
			return $rsp;
		}

		$rsp = db_insert('boundaryissues_photos', array(
			'wof_id' => $esc_wof_id,
			'user_id' => $esc_user_id,
			'type' => 'flickr',
			'info' => $info_json,
			'sort' => 0,
			'created' => date('Y-m-d H:i:s')
		));
		return $rsp;
	}

	########################################################################

	function wof_photos_get($wof_id){
		$esc_wof_id = intval($wof_id);
		$rsp = db_fetch("
			SELECT *
			FROM boundaryissues_photos
			WHERE wof_id = $esc_wof_id
			ORDER BY sort
		");
		if (! $rsp['ok']){
			return $rsp;
		}

		$photos = array();
		foreach ($rsp['rows'] as $photo){
			$photo['info'] = json_decode($photo['info'], 'as hash');
			if ($photo['type'] == 'flickr'){
				$photo['src'] = wof_photos_src($photo);
			}
			$photos[] = $photo;
		}

		return array(
			'ok' => 1,
			'photos' => $photos
		);
	}
	
	########################################################################
	
	function wof_photos_src($photo){
		if ($photo['type'] == 'flickr'){
			return wof_photos_flickr_src($photo['info']);
		}
	}

	# the end
