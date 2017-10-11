<?php

include('include/init.php');
loadlib('wof_pipeline');

if (! features_is_enabled('pipeline')) {
	error_404();
}

$id = get_int64('id');
if (! $id && users_acl_check_access($GLOBALS['cfg']['user'], 'can_upload_pipelines')) {
	$upload_formats = '.zip';
	$GLOBALS['smarty']->assign('upload_formats', $upload_formats);

	$crumb = crumb_generate('api', 'wof.pipeline.create');
	$GLOBALS['smarty']->assign("crumb", $crumb);

	$slack_handle = users_settings_get_single($GLOBALS['cfg']['user'], 'slack_handle');
	if ($slack_handle) {
		$GLOBALS['smarty']->assign('slack_handle', $slack_handle);
	}
	$GLOBALS['smarty']->display('page_create_pipeline.txt');
} else if ($id && users_acl_check_access($GLOBALS['cfg']['user'], 'can_view_pipelines')) {
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

	$GLOBALS['smarty']->assign('pipeline_repo', $pipeline['meta']['repo']);
	$GLOBALS['smarty']->assign('pipeline_branch', "pipeline-{$pipeline['id']}");

	$crumb_update = crumb_generate('api', 'wof.pipeline.update');
	$GLOBALS['smarty']->assign("crumb_update", $crumb_update);

	$rsp = wof_pipeline_log_dump($id);
	if ($rsp['logs']) {
		$GLOBALS['smarty']->assign('logs', $rsp['logs']);
	}

	$GLOBALS['smarty']->display('page_pipeline.txt');
} else {
	error_404();
}
