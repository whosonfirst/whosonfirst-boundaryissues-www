<?php

	function json_schema_fields($filename){
		$path = realpath(FLAMEWORK_INCLUDE_DIR . "../../schema/json/$filename");
		if (!file_exists($path)) {
			log_error("Could not find JSON schema '$filename'");
			return '';
		} else {
			$json = file_get_contents($path);
			$schema = json_decode($json, true);
			return json_schema_fields_object($schema);
		}
	}

	function json_schema_fields_object($object){
		$html = '';
		if ($object['allOf']) {
			foreach ($object['allOf'] as $part_of){
				if ($part_of['$ref']){
					// Handle file inclusion here
				} else {
					$object = array_merge($object, $part_of);
				}
			}
		}
		//dumper($object);
		if ($object['properties']) {
			$html .= "<table>\n";
			foreach ($object['properties'] as $property => $details){
				$html .= "<tr>\n";
				$details['property'] = $property;
				$html .= "<th>$property</th>\n<td>";
				if ($details['type'] == 'string'){
					$html .= json_schema_fields_string($details);
				} else if ($details['type'] == 'integer'){
					$html .= json_schema_fields_integer($details);
				} else if ($details['type'] == 'object'){
					$html .= json_schema_fields_object($details);
				}
				$html .= "</td>\n</tr>\n";
			}
			$html .= "</table>\n";
		}
		return $html;
	}

	function json_schema_fields_string($details) {
		return "<input type=\"text\" name=\"{$details['property']}\">";
	}

	function json_schema_fields_integer($details) {
		return "<input type=\"number\" name=\"{$details['property']}\">";
	}
