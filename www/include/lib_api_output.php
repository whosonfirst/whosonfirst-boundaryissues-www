<?php

	loadlib("api_output_utils");

	# Hey look! Running code!!

	$format = api_output_get_format();

	if (! $format){
		$format = $GLOBALS['cfg']['api']['default_format'];
	}

	loadlib("api_output_{$format}");

	#################################################################

	function api_output_get_format(){

		$format = null;
		$possible = null;
	
		if (request_isset('format')){
			$possible = request_str('format');
		}

		elseif (function_exists('getallheaders')){

			$headers = getallheaders();

			if (isset($headers['Accept'])){

				foreach (explode(",", $headers['Accept']) as $what){

					list($type, $q) = explode(";", $what, 2);

					if (preg_match("!^application/(\w+)$!", $type, $m)){
						$possible = $m[1];
						break;
					}
				}
			}
		}

		else {}

		if ($possible){

			if (in_array($possible, $GLOBALS['cfg']['api']['formats'])){
				$format = $possible;
			}
		}

		return $format;
	}

	#################################################################

	# the end
