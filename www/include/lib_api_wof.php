<?php
	loadlib('wof_upsert');

	function api_wof_upload(){

		if (!$_FILES["upload_file"]){
			api_output_error(400, 'Please include an upload_file.');
		}

		$rsp = wof_upsert($_FILES["upload_file"]["tmp_name"]);
		if (!$rsp['ok']){
			$error = $rsp['error'] || 'Upload failed for some reason.';
			api_output_error(400, $error);
		}
		$out = array(
			'result' => $rsp
		);
		api_output_ok($out);
	}
