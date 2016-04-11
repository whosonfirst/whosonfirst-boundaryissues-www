<?php

	include("init_local.php");
	loadlib("logstash");

	# TO DO: run as an offline task... (20160411/thisisaaronland)

	$now = time();

	omgwtf("hello from {$now}");
	exit();
?>	