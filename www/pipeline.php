<?php

include('include/init.php');
loadlib('wof_pipeline');

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
$GLOBALS['smarty']->assign('pipeline_url', '#');

$rsp = wof_pipeline_log_dump($id);
if ($rsp['logs']) {
	$GLOBALS['smarty']->assign('logs', $rsp['logs']);
}

$GLOBALS['smarty']->display('page_pipeline.txt');
exit();
