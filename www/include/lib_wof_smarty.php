<?php

	# to do: add a '_make_smarty_safe' or equivalent function...
	# (20160318/thisisaaronland)

	########################################################################

	function wof_pv(&$props, $key){
		return wof_smarty_properties_value($props, $key);
	}

	########################################################################

	# this exists entirely to account for the part where smarty hates colons...
	# for example: {$properties|@wof_smarty_properties_value:"edtf:cessation"|@escape}

	function wof_smarty_properties_value(&$props, $key){

		if (! isset($props[$key])){
			return null;
		}

		return $props[$key];
	}

	########################################################################

	# the end