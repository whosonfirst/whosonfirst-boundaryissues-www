<?php

	#################################################################

	function mapzen_users_get_by_oauth_token($token){

		$enc_token = AddSlashes($token);

		$sql = "SELECT * FROM MapzenUsers WHERE oauth_token='{$enc_token}'";
		return db_single(db_fetch($sql));
	}

	#################################################################

	function mapzen_users_get_by_mapzen_id($mapzen_id){

		$enc_id = AddSlashes($mapzen_id);

		$sql = "SELECT * FROM MapzenUsers WHERE mapzen_id='{$enc_id}'";
		return db_single(db_fetch($sql));
	}

	#################################################################

	function mapzen_users_get_by_user_id($user_id){

		$enc_id = AddSlashes($user_id);

		$sql = "SELECT * FROM MapzenUsers WHERE user_id='{$enc_id}'";
		return db_single(db_fetch($sql));
	}

	#################################################################

	function mapzen_users_create_user($user){

		$hash = array();

		foreach ($user as $k => $v){
			$hash[$k] = AddSlashes($v);
		}

		$rsp = db_insert('MapzenUsers', $hash);

		if (! $rsp['ok']){
			return $rsp;
		}

		# $cache_key = "mapzen_user_{$user['mapzen_id']}";
		# cache_set($cache_key, $user, "cache locally");

		$cache_key = "mapzen_user_{$user['id']}";
		cache_set($cache_key, $user, "cache locally");

		$rsp['mapzen_user'] = $user;
		return $rsp;
	}

	#################################################################

	function mapzen_users_update_user(&$mapzen_user, $update){

		$hash = array();

		foreach ($update as $k => $v){
			$hash[$k] = AddSlashes($v);
		}

		$enc_id = AddSlashes($mapzen_user['user_id']);
		$where = "user_id='{$enc_id}'";

		$rsp = db_update('MapzenUsers', $hash, $where);

		if ($rsp['ok']){

			$mapzen_user = array_merge($mapzen_user, $update);
			$rsp['mapzen_user'] = $mapzen_user;

			# $cache_key = "mapzen_user_{$mapzen_user['mapzen_id']}";
			# cache_unset($cache_key);

			$cache_key = "mapzen_user_{$mapzen_user['user_id']}";
			cache_unset($cache_key);
		}

		return $rsp;
	}

	#################################################################

	# the end
