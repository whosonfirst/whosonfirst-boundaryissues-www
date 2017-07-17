<?php

include('include/init.php');
loadlib('wof_pipeline');

if (! users_acl_check_access($GLOBALS['cfg']['user'], 'can_view_pipelines')) {
	error_404();
}

$id = get_int64('id');
$rsp = wof_pipeline_get($id);

if (! $rsp['ok'] ||
    ! $rsp['pipeline']) {
	error_404();
}

$pipeline = $rsp['pipeline'];

$GLOBALS['smarty']->assign('pipeline_id', $id);
$GLOBALS['smarty']->assign('pipeline_type', $pipeline['type']);
$GLOBALS['smarty']->assign('pipeline_filename', $pipeline['filename']);
$GLOBALS['smarty']->assign('pipeline_phase', $pipeline['phase']);

$rsp = wof_pipeline_log_dump($id);
if ($rsp['logs']) {
	$GLOBALS['smarty']->assign('logs', $rsp['logs']);
}

$GLOBALS['smarty']->display('page_pipeline.txt');
exit();
