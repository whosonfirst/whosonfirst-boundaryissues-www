<?php

	include("init_local.php");

	loadlib("logstash");
	loadlib("cli");

	loadlib("offline_tasks");
	loadlib("offline_tasks_gearman");

	$spec = array(
		"offline" => array("flag" => "o", "required" => 0, "boolean" => 1, "help" => "run this as an offline task"),
	);

	# TO DO: run as an offline task... (20160411/thisisaaronland)

	$opts = cli_getopts($spec);

	$now = time();
	$msg = "hello from {$now}";

	if ($opts['offline']){

		# WHY DO I HAVE TO DO THIS...
		offline_tasks_gearman_init();

		$data = array("message" => "{$msg} OFFLINE");
		$rsp = offline_tasks_schedule_task("omgwtf", $data);
	}

	else {
		$rsp = omgwtf($msg);
	}

	dumper($rsp);
	exit();
?>	