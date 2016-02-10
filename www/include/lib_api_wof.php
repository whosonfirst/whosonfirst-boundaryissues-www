<?php
	loadlib('wof_upsert');
	loadlib('wof_venue');

	function api_wof_upload(){

		if (!$_FILES["upload_file"]){
			api_output_error(400, 'Please include an upload_file.');
		}

		$rsp = wof_upsert($_FILES["upload_file"]["tmp_name"]);
		if (!$rsp['ok'] ||
		    !$rsp['geojson_url']){
			$error = $rsp['error'] || 'Upload failed for some reason.';
			api_output_error(400, $error);
		}
		api_output_ok($rsp);
	}
