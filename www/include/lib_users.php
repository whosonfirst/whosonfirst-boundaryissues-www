<?php

	#################################################################

	#
	# create a user record. the fields pass in $user
	# ARE NOT ESCAPED.
	#

	function users_create_user($user){

		#
		# set up some extra fields first
		#

		loadlib('random');

		$user['password'] = passwords_encrypt_password($user['password']);
		$user['created'] = time();
		$user['conf_code'] = random_string(24);

		$user['cluster_id'] = users_assign_cluster_id();


		#
		# now create the escaped version
		#

		$hash = array();
		foreach ($user as $k => $v){
			$hash[$k] = AddSlashes($v);
		}

		$ret = db_insert_accounts('users', $hash);

		if (!$ret['ok']) return $ret;


		#
		# cache the unescaped version
		#

		$user['id'] = $ret['insert_id'];

		cache_set("USER-{$user['id']}", $user);

		return array(
			'ok'	=> 1,
			'user'	=> $user,
		);
	}

	#################################################################

	#
	# update multiple fields on an user record. the hash passed
	# in $update IS NOT ESCAPED.
	#

	function users_update_user(&$user, $update){

		$hash = array();
		foreach ($update as $k => $v){
			$hash[$k] = AddSlashes($v);
		}

		$ret = db_update_accounts('users', $hash, "id={$user['id']}");

		if (!$ret['ok']) return $ret;

		cache_unset("USER-{$user['id']}");

		return array(
			'ok' => 1,
		);
	}

	#################################################################

	function users_update_password(&$user, $new_password){

		$enc_password = passwords_encrypt_password($new_password);

		return users_update_user($user, array(
			'password' => AddSlashes($enc_password),
		));
	}

	#################################################################

	function users_delete_user(&$user){

		return users_update_user($user, array(
			'deleted'	=> time(),
			'email'		=> $user['email'] . '.DELETED',

			# reset the password here ?
		));
	}

	#################################################################

	function users_reload_user(&$user){

		$user = users_get_by_id($user['id']);
	}

	#################################################################

	function users_get_by_id($id){

		$sql = "SELECT * FROM users WHERE id=" . intval($id);

		$rsp = db_fetch_accounts($sql);
		$user = db_single($rsp);

		cache_set("USER-{$user['id']}", $user);

		return $user;
	}

	#################################################################

	function users_get_by_email($email){

		$enc_email = AddSlashes($email);
		$sql = "SELECT * FROM users WHERE email='{$enc_email}'";

		$rsp = db_fetch_accounts($sql);
		return db_single($rsp);
	}

	#################################################################

	function users_get_by_login($email, $password){

		$user = users_get_by_email($email);

		if (!$user){
			return null;
		}

		if ($user['deleted']){
			return null;
		}

		if (! passwords_validate_password_for_user($password, $user)){
			return null;
		}

		return $user;
	}

	#################################################################

	function users_get_gravatar($email) {
		$md5 = md5($email);
		return "https://s.gravatar.com/avatar/$md5?s=48&r=pg";
	}

	#################################################################

	function users_is_email_taken($email){

		$enc_email = AddSlashes($email);
		$sql = "SELECT id FROM users WHERE email='{$enc_email}' AND deleted=0";

		$rsp = db_fetch_accounts($sql);
		$row = db_single($rsp);

		return $row['id'] ? 1 : 0;
	}

	#################################################################

	function users_is_username_taken($username){

		$enc_username = AddSlashes($username);

		$sql = "SELECT id FROM users WHERE username='{$enc_username}' AND deleted=0";
		$rsp = db_fetch_accounts($sql);

		$row = db_single($rsp);
		return $row['id'] ? 1 : 0;
	}

	#################################################################

	function users_get_by_password_reset_code($code){

		$enc_code = AddSlashes($code);

		$sql = "SELECT * FROM users_password_reset WHERE reset_code='{$enc_code}'";
		$rsp = db_fetch_accounts($sql);
		$row = db_single($rsp);

		if (!$row){
			return null;
		}

		return users_get_by_id($row['user_id']);
	}

	#################################################################

	function users_purge_password_reset_codes(&$user){

		$enc_user = intval($user[id]);
		$sql = "DELETE FROM users_password_reset WHERE user_id=$enc_user";

		$rsp = db_write_accounts($sql);

		return $rsp['ok'];
	}

	#################################################################

	function users_send_password_reset_code(&$user){

		$code = users_generate_password_reset_code($user);
		if (!$code) return 0;

		$GLOBALS['smarty']->assign('code', $code);

		email_send(array(
			'to_email'	=> $user['email'],
			'template'	=> 'email_password_reset.txt',
		));

		return 1;
	}

	#################################################################

	function users_generate_password_reset_code(&$user){

		loadlib('random');

		users_purge_password_reset_codes($user);

		$code = '';

		while (!$code){

			$code = random_string(32);
			$enc_code = AddSlashes($code);

			$sql = "SELECT 1 FROM users_password_reset WHERE reset_code='{$enc_code}'";
			$rsp = db_fetch_accounts($sql);

			if (db_single($rsp)){
				$code = '';
			}

			break;
		}

		$rsp = db_insert_accounts('users_password_reset', array(
			'user_id'	=> $user['id'],
			'reset_code'	=> $enc_code,
			'created'	=> time(),
		));

		if (!$rsp['ok']){
			return null;
		}

		return $code;
	}

	#################################################################

	function users_assign_cluster_id(){

		if ($GLOBALS['cfg']['db_enable_poormans_federation']){
			return 1;
		}

		# TO DO: an actual cluster ID if federated

		return 1;
	}

	########################################################################

	function users_ensure_terms_accepted($redir = ''){

		if ($GLOBALS['cfg']['user']['terms_accepted']){
			return;
		}

		$url = "{$GLOBALS['cfg']['abs_root_url']}terms";
		if ($redir) {
			$url .= "?redir=$redir";
		}

		header("location: $url");
		exit;
	}

	# the end
