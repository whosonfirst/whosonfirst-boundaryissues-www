<?php

	##############################################################################

	function api_utils_ensure_pagination_args(&$args){

		if ($page = request_int32("page")){
			$args['page'] = $page;
		}

		if ($per_page = request_int32("per_page")){
			$args['per_page'] = $per_page;
		}

		if (! $args['page']){
			$args['page'] = 1;
		}

		if (! $args['per_page']){
			$args['per_page'] = $GLOBALS['cfg']['api_per_page_default'];
		}

		else if ($args['per_page'] > $GLOBALS['cfg']['api_per_page_max']){
			$args['per_page'] = $GLOBALS['cfg']['api_per_page_max'];
		}

		# note the pass by ref
	}

	##############################################################################

	function api_utils_ensure_pagination_results(&$out, &$pagination){

		$out['total'] = $pagination['total_count'];
		$out['page'] = $pagination['page'];
		$out['per_page'] = $pagination['per_page'];
		$out['pages'] = $pagination['page_count'];

		# note the pass by ref
	}
	
	##############################################################################

	function api_utils_features_ensure_enabled($f){
		if (! features_is_enabled($f)){
			api_output_error(502, "This feature is disabled");
		}
	}

	##############################################################################

	# https://secure.php.net/manual/en/features.file-upload.php
	# https://secure.php.net/manual/en/features.file-upload.post-method.php
	function api_utils_ensure_upload($param, $more=array()){
		$rsp = api_utils_get_upload($param, $more);
		if (! $rsp['ok']){
			api_output_error(400, $rsp['error']);
		}
		return $rsp;
	}
	########################################################################
	function api_utils_get_upload($param, $more=array()){
		$defaults = array(
			'ensure_mimetype' => array()
		);
		$more = array_merge($defaults, $more);
		if (! isset($_FILES[$param])){
			return array('ok' => 0, 'error' => "Missing upload parameter");
		}
		if (! is_array($_FILES[$param])){
			return array('ok' => 0, 'error' => "Invalid upload parameter");
		}
		$upload = $_FILES[$param];
		if (! isset($upload['error'])){
			return array('ok' => 0, 'error' => "Missing upload error response");
		}
		if (is_array($upload['error'])){
			return array('ok' => 0, 'error' => "Invalid upload error response");
		}
		switch ($upload['error']) {
			case UPLOAD_ERR_OK:
				break;
			case UPLOAD_ERR_NO_FILE:
				return array('ok' => 0, 'error' => "Missing body");
			case UPLOAD_ERR_INI_SIZE:
				# pass
			case UPLOAD_ERR_FORM_SIZE:
				return array('ok' => 0, 'error' => "Exceeded filesize");
			default:
				return array('ok' => 0, 'error' => "INVISIBLE ERROR CAT");
		}
		if (! is_uploaded_file($upload['tmp_name'])){
			return array('ok' => 0, 'error' => "Invalid upload file name");
		}
		if ($upload['size'] == 0){
			return array('ok' => 0, 'error' => "Invalid file size");
		}
		$finfo = finfo_open(FILEINFO_MIME_TYPE);
		$mime = finfo_file($finfo, $upload['tmp_name']);
		if (count($more['ensure_mimetype'])){
			if (! in_array($mime, $more['ensure_mimetype'])){
				return array('ok' => 0, 'error' => "Invalid mime type");
			}
		}
		return array('ok' => 1, 'upload' => $upload, 'mimetype' => $mime);
	}
########################################################################

	# the end
