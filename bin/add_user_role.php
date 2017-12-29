<?php

	include 'init_local.php';

	loadlib('users');
	loadlib('users_acl');

	if ($argc != 3) {
		die("Usage: php add_user_role.php [email] [role]\n");
	}

	$email = $argv[1];
	$role = $argv[2];

	$user = users_get_by_email($email);

	if (! $user) {
		die("User with email '$email' not found.\n");
	}

	$existing_roles = users_acl_get_roles($user);
	if (in_array($role, $existing_roles)) {
		die("User '$email' already has role '$role'.\n");
	}

	$confirm = readline("Add role '$role' to user '$email'? [Y] ");

	export_var($confirm);

	if (strtolower($confirm) != "y" &&
	    $confirm != "") {
		die("Cancelling.\n");
	}

	users_acl_grant_role($user, $role);

	echo "Done.\n";
