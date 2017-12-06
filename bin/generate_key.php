<?php

	include("init_local.php");

	loadlib("crypto");

	echo crypto_generate_key();
	echo "\n";
	exit();
