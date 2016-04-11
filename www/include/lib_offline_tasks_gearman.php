<?php

	loadlib("gearman_client");
	loadlib("gearman_worker");

	$GLOBALS['offline_tasks_hooks']['schedule'] = 'gearman_client_schedule_job';
	# $GLOBALS['offline_tasks_hooks']['execute'] = 'gearman_execute_job';

	########################################################################
	
	# the end