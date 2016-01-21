<?php
	loadlib('wof_upload');

	function api_wof_upload(){
		 $rsp = wof_upload();
		 if ($rsp['ok']){
			 api_output_error(400, 'Upload failed');
		 }
		 $out = array(
		      'result' => $rsp
		 );
		 api_output_ok($out);
	}
