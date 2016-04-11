<?php

	$root = dirname(dirname(__FILE__));
	ini_set("include_path", "{$root}/www:{$root}/www/include:/usr/share/php");

	include("include/init.php");	

	# the end