<?php

	# to do: add a '_make_smarty_safe' or equivalent function...
	# (20160318/thisisaaronland)

	########################################################################

	function wof_ancestor(&$props, $pt){

		if (! isset($props['wof:hierarchy'])){
			return null;
		}

		$hiers = $props['wof:hierarchy'];

		if (count($hiers) > 1){
			return null;
		}

		$hiers = $hiers[0];
		$k = "{$pt}_id";

		if (! isset($hiers[$k])){
			return null;
		}

		return $hiers[$k];
	}

	########################################################################

	function wof_pv(&$props, $key){
		$value = wof_smarty_properties_value($props, $key);
		if (substr($key, 0, 5) == 'edtf:' &&
		    $value == 'uuuu') {
			// Treat 'uuuu' EDTF date values as equivalent to no property value
			return null;
		}
		return $value;
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
