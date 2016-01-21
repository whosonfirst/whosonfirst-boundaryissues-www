<?php
	include('include/init.php');

	login_ensure_loggedin();

	$crumb_key = 'upload';
	$smarty->assign("crumb_key", $crumb_key);

	$GLOBALS['smarty']->display('page_index.txt');
	exit();

?>
