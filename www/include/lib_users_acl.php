<?php
	/*

	How to configure ACLs:

	This goes in your config_local.php

	$GLOBALS['cfg']['users_acl'] = array(
		'staff' => array(
			'can_edit_all_repos' // Staff can edit any repo
		),
		'ny_venue_editor' => array(
			// Access is restricted to NY Venues
			'can_edit_whosonfirst-data-venue-us-ny'
		)
	);

	*/

	loadlib('users');

	########################################################################

	function users_acl_can_edit($user, $repo) {
		if (users_acl_check_access($user, 'can_edit_all_repos')) {
			return true;
		} else if (users_acl_check_access($user, 'can_edit_' . $repo)) {
			return true;
		}
		return false;
	}

	########################################################################

	function users_acl_check_access($user, $capability) {

		// What roles have been assigned to the user?
		$roles = users_acl_get_roles($user);

		// Which capabilities are afforded to those roles?
		$capabilities = users_acl_get_capabilities($roles);

		// Is the capability is in the list of things the user can do?
		return in_array($capability, $capabilities);
	}

	########################################################################

	function users_acl_get_roles($user) {
		$esc_user_id = addslashes($user['id']);
		$rsp = db_fetch("
			SELECT user_role
			FROM users_roles
			WHERE user_id = $esc_user_id
		");
		if (! $rsp['ok']) {
			// So here maybe I should raise more of a fuss, in case
			// the database table hasn't been added or something?
			// For now it just quietly says "nope."
			// (20161212/dphiffer)
			return array();
		}

		$roles = array();
		foreach ($rsp['rows'] as $row) {
			$roles[] = $row['user_role'];
		}
		return $roles;
	}

	########################################################################

	function users_acl_get_capabilities($user_roles) {
		$capabilities = array();
		foreach ($GLOBALS['cfg']['users_acl'] as $role => $caps) {
			if (in_array($role, $user_roles)) {
				$capabilities = array_merge($capabilities, $caps);
			}
		}
		return $capabilities;
	}

	########################################################################

	function users_acl_grant_role($user, $role) {
		$esc_user_id = addslashes($user['id']);
		$esc_role = addslashes($role);
		$rsp = db_insert('users_roles', array(
			'user_id' => $esc_user_id,
			'user_role' => $esc_role
		));
		return $rsp;
	}

	# the end
