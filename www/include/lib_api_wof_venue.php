<?php
	loadlib('wof_venue');

	function api_wof_venue_create() {

		if (! $_POST['venue']) {
			api_output_error(400, "Please include a 'venue' parameter.");
		}

		$rsp = wof_venue_create($_POST['venue']);
		if (! $rsp['ok'] ||
				! $rsp['geojson_url']) {
			$error = $rsp['error'] || 'Venue creation failed for some reason.';
			api_output_error(400, $error);
		}
		api_output_ok($rsp);
	}
