<?php

	#################################################################

	function github_users_get_by_oauth_token($token){

		$enc_token = AddSlashes($token);

		$sql = "SELECT * FROM GithubUsers WHERE oauth_token='{$enc_token}'";
		return db_single(db_fetch($sql));
	}

	#################################################################

	function github_users_get_by_user_id($user_id){

		$enc_id = AddSlashes($user_id);

		$sql = "SELECT * FROM GithubUsers WHERE user_id='{$enc_id}'";
		return db_single(db_fetch($sql));
	}
	
	#################################################################

	function github_users_get_by_github_id($github_id){

		$enc_id = AddSlashes($github_id);

		$sql = "SELECT * FROM GithubUsers WHERE github_id='{$enc_id}'";
		return db_single(db_fetch($sql));
	}

	#################################################################

	function github_users_create_user($user){

		$hash = array();

		foreach ($user as $k => $v){
			$hash[$k] = AddSlashes($v);
		}

		$rsp = db_insert('GithubUsers', $hash);

		if (! $rsp['ok']){
			return null;
		}

		# $cache_key = "github_user_{$user['github_id']}";
		# cache_set($cache_key, $user, "cache locally");

		$cache_key = "github_user_{$user['id']}";
		cache_set($cache_key, $user, "cache locally");

		return $user;
	}

	#################################################################

	function github_users_update_user(&$github_user, $update){

		$hash = array();
		
		foreach ($update as $k => $v){
			$hash[$k] = AddSlashes($v);
		}

		$enc_id = AddSlashes($github_user['user_id']);
		$where = "user_id='{$enc_id}'";

		$rsp = db_update('GithubUsers', $hash, $where);

		if ($rsp['ok']){

			$github_user = array_merge($github_user, $update);

			# $cache_key = "github_user_{$github_user['github_id']}";
			# cache_unset($cache_key);

			$cache_key = "github_user_{$github_user['user_id']}";
			cache_unset($cache_key);
		}

		return $rsp;
	}

	#################################################################

	# the end
