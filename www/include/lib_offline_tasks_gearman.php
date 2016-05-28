<?php

	loadlib("offline_tasks");
	loadlib("gearman");

	function offline_tasks_gearman_init(){

		$GLOBALS['offline_tasks_hooks']['schedule'] = 'gearman_client_schedule_job';
		# $GLOBALS['offline_tasks_hooks']['execute'] = 'gearman_execute_job';
	}

	offline_tasks_gearman_init();

	########################################################################

	# the end
