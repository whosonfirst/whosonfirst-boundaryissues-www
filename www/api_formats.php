<?php

	include("include/init.php");

	features_ensure_enabled(array(
		"api",
		"api_documentation",
	));

	$default = $GLOBALS['cfg']['api']['default_format'];

	$formats = $GLOBALS['cfg']['api']['formats'];
	sort($formats);

	$GLOBALS['smarty']->assign("default", $default);
	$GLOBALS['smarty']->assign("formats", $formats);

	$GLOBALS['smarty']->display("page_api_formats.txt");
	exit();

?>
