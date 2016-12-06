<?php

	loadlib('users');
	loadlib('mapzen_users');

	########################################################################

	function users_acl_can_edit($user_id) {
		return users_acl_check_access($user_id, 'can-edit');
	}

	########################################################################

	function users_acl_check_access($user_id, $access_type) {

		// First check if the 'admin' flag is set
		$mapzen_user = mapzen_users_get_by_user_id($user_id);
		if ($mapzen_user && $mapzen_user['admin']) {
			return true;
		}

		$user = users_get_by_id($user_id);
		if (! $user) {
			// User does not exist??
			return false;
		}

		$username = $user['username'];

		if (! $GLOBALS['cfg']['users_acl'][$username]) {
			// new phone who dis
			return false;
		}

		if (in_array($access_type, $GLOBALS['cfg']['users_acl'][$username])) {
			// winner winner chicken dinner
			return true;
		}

		// Nope.
		return false;
	}

	########################################################################

	# the end
