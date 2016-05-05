<?php

	include("init_local.php");
	loadlib('wof_save');

	$rsp = wof_save_pending();
	print_r($rsp);

	exit();
?>
