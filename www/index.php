<?php
	include('include/init.php');
	loadlib('wof_events');

	if ($GLOBALS['cfg']['user']) {
		$events = wof_events_for_user($GLOBALS['cfg']['user']['id']);
		$GLOBALS['smarty']->assign_by_ref('events', $events);
	}

	$GLOBALS['smarty']->display('page_index.txt');
	exit();

?>
