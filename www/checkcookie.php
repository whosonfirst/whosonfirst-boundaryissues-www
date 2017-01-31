<?php
	include("include/init.php");


	#
	# do we have a valid cookie set?
	#

	if (!login_check_login()){

		$smarty->display("page_error_cookie.txt");
		exit;
	}


	#
	# where shall we bounce to?
	#

	$redir = request_str('redir');
	$url = "{$GLOBALS['cfg']['abs_root_url']}terms/";

	if ($redir){
		$url .= "?redir={$redir}";
	}

	header("location: {$url}");
	exit;
