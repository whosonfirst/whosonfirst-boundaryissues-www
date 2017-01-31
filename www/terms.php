<?php

	include('include/init.php');

	$terms_accepted = post_bool('terms_accepted');
	$redir = request_str("redir");
	$GLOBALS['smarty']->assign('redir', $redir);

	if ($terms_accepted && $GLOBALS['cfg']['user']) {
		users_update_user($GLOBALS['cfg']['user'], array(
			'terms_accepted' => 1
		));

		$url = $GLOBALS['cfg']['abs_root_url'];

		if ($redir){
			if (substr($redir, 0, 1) == '/') $redir = substr($redir, 1);
			$url .= $redir;
		}

		#
		# go!
		#

		header("location: {$url}");
		exit;
	}

	if ($GLOBALS['cfg']['user'] &&
	    ! $GLOBALS['cfg']['user']['accepted_tos']) {
		$GLOBALS['smarty']->display('page_terms_accept.txt');
	} else {
		$GLOBALS['smarty']->display('page_terms.txt');
	}
