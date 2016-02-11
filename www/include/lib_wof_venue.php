<?php
	loadlib('wof_utils');
	loadlib('artisanal_integers');
	loadlib('json_schema_fields');

	function wof_venue_create() {

	}

	function wof_venue_fields() {
		return json_schema_fields('whosonfirst.schema');
	}
