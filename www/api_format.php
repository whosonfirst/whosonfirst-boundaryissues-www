<?php

	include("include/init.php");

	features_ensure_enabled(array(
		"api",
		"api_documentation",
	));

	$default = $GLOBALS['cfg']['api']['default_format'];
	$formats = $GLOBALS['cfg']['api']['formats'];
	$format = get_str("format");

	if (! $format){
		error_404();
	}

	if (! in_array($format, $formats)){
		error_404();
	}

	$GLOBALS['smarty']->assign("default", $default);
	$GLOBALS['smarty']->assign("format", $format);

	$GLOBALS['smarty']->display("page_api_format.txt");
	exit();

?>
