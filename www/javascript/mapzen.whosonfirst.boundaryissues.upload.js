var mapzen = mapzen || {};
mapzen.whosonfirst = mapzen.whosonfirst || {};
mapzen.whosonfirst.boundaryissues = mapzen.whosonfirst.boundaryissues || {};

// for the time being this assumes jQuery is present
// that decision may change some day but not today
// (20160121/thisisaaronland)

mapzen.whosonfirst.boundaryissues.upload = (function(){

	var $form,
	    $result,
	    $preview_map,
	    $preview_props,
	    geojson_file,
	    upload_is_ready = false,
	    properties_are_ready = false,
	    is_collection,
	    VenueIcon,
	    poi_icon_base;

	var esc_str = mapzen.whosonfirst.php.htmlspecialchars;

	var self = {

		setup_upload: function(){

			// Get some jQuery references to our top-level elements
			$form = $('#upload-form');
			$result = $('#upload-result');
			$preview_map = $('#upload-preview-map');
			$preview_props = $('#upload-preview-props');

			// Grab a reference to the file input's data when its onchange fires
			$form.find('input[name=geojson_file]').on('change', function(e){
				geojson_file = e.target.files[0];
				self.show_preview(geojson_file);
			});

			// Intercept the form submit event and upload the file via API
			$form.submit(function(e){
				e.preventDefault();
				if (! upload_is_ready ||
				    ! properties_are_ready) {
					return;
				}
				self.post_file();
			});
		},

		setup_properties_lookup: function() {
			var lookup_url = mapzen.whosonfirst.boundaryissues.utils.abs_root_urlify('/meta/properties_lookup.json');
			$.get(lookup_url, function(lookup) {
				self.properties_lookup = lookup;
				properties_are_ready = true;
			});
		},

		show_preview: function(){

			// Read the file and display a preview map prior to uploading
			var reader = new FileReader();
			reader.onload = function(e){
				try {
					var geojson = JSON.parse(reader.result);
				} catch(e) {
					$result.html(e);
					upload_is_ready = false;
					return;
				}

				upload_is_ready = true;
				$('#upload-btn').addClass('btn-primary');
				$('#upload-btn').attr('disabled', false);

				$preview_map.removeClass('hidden');

				var scene  = mapzen.whosonfirst.boundaryissues.utils.abs_root_urlify('/tangram/refill.yaml');
				mapzen.whosonfirst.leaflet.tangram.scenefile(scene);

				var swlat = 37.70120736474139;
				var swlon = -122.68707275390624;
				var nelat = 37.80924146650164;
				var nelon = -122.21912384033203;

				// Show the preview map
				var map = mapzen.whosonfirst.leaflet.tangram.map_with_bbox(
					'upload-preview-map',
					swlat, swlon, nelat, nelon
				);

				// Clear the map if a feature is already on there
				map.eachLayer(function(layer) {
					if (layer.feature) {
						map.removeLayer(layer);
					}
				});

				if (geojson.type == "Feature") {
					is_collection = false;
					mapzen.whosonfirst.leaflet.fit_map(map, geojson);
					self.show_feature_preview(map, geojson);
					self.show_props_preview(geojson);
				} else if (geojson.type == "FeatureCollection") {
					is_collection = true;
					self.show_collection_preview(map, geojson);
					self.show_collection_props_preview(geojson);
				}
			}

			// Load up the file to kick off the preview
			if (geojson_file) {
				reader.readAsText(geojson_file);
				$result.html('This is just a preview. You still have to hit the upload button.');
			} else {
				mapzen.whosonfirst.log.error('No geojson_file to preview.');
			}
		},

		show_collection_preview: function(map, collection) {
			mapzen.whosonfirst.leaflet.fit_map(map, collection);
			var feature;
			for (var i = 0; i < collection.features.length; i++) {
				feature = collection.features[i];
				self.show_feature_preview(map, feature);
			}
		},

		show_feature_preview: function(map, feature) {
			if (feature.properties['wof:placetype'] == 'venue') {
				var lat = parseFloat(feature.properties['geom:latitude']);
				var lng = parseFloat(feature.properties['geom:longitude']);
				marker = new L.Marker([lat, lng], {
					icon: new VenueIcon()
				}).addTo(map);
			} else {
				var style = mapzen.whosonfirst.leaflet.styles.consensus_polygon();
				mapzen.whosonfirst.leaflet.draw_poly(map, feature, style);
			}
		},

		show_props_preview: function(geojson) {
			var props = self.get_geojson_props(geojson);
			if (! props) {
				return;
			}
			var groups = [];
			var group_props = {};
			$.each(props, function(i, orig_prop) {
				var prop = self.get_translated_property(orig_prop);
				var group = '_no_group_';
				var group_match = prop.match(/^([a-z0-9_]+)\:/i);
				if (group_match) {
					group = group_match[1];
				}
				if (! group_props[group]) {
					group_props[group] = [];
					groups.push(group);
				}
				var html = self.get_property_html(prop, orig_prop);
				group_props[group].push(html);
			});

			groups.sort();
			if (! group_props._no_group_) {
				group_props._no_group_ = [];
				groups.push('_no_group_');
			} else {
				// Always put the "Other" properties last
				groups.shift();
				groups.push('_no_group_');
			}

			var html = '<h3>Include properties</h3>';
			html += '<div class="headroom"><input type="checkbox" class="property" id="upload-geometry" name="geometry" value="1" checked="checked"><label for="upload-geometry">Update geometry</label></div>';

			$.each(groups, function(i, group) {
				console.log(group);
				if (group != '_no_group_' &&
				    group_props[group].length == 1) {
					group_props._no_group_.push(group_props[group][0]);
					return;
				} else if (group_props[group].length == 0) {
					return;
				}
				group_props[group].sort();
				html += '<div class="property-group col-md-4 headroom">';
				if (group == '_no_group_') {
					html += '<strong>Other properties</strong>';
				} else {
					html += '<strong>' + group + ' properties</strong>';
				}
				html += '<ul class="upload-properties">';
				$.each(group_props[group], function(j, item) {
					html += '<li>' + item + '</li>';
				});
				html += '</ul>';
				html += '</div>';
			});

			$preview_props.html(html);
		},

		get_geojson_props: function(geojson) {
			if (geojson.type == 'Feature') {
				return Object.keys(geojson.properties);
			} else if (geojson.features) {
				var properties = [];
				$.each(geojson.features, function(i, feature) {
					var keys = Object.keys(feature.properties);
					$.each(keys, function(j, key) {
						if (properties.indexOf(key) == -1) {
							properties.push(key);
						}
					});
				});
				return properties;
			}
			return null;
		},

		get_translated_property: function(prop) {
			if (self.properties_lookup[prop]) {
				return self.properties_lookup[prop];
			} else {
				return prop;
			}
		},

		get_property_html: function(prop, orig_prop) {
			var prop_esc = esc_str(prop);
			var orig_esc = esc_str(orig_prop);
			var aside = '';
			if (prop != orig_prop) {
				aside = ' <small><i>' + orig_esc + '</i></small>';
			}
			return '<input type="checkbox" class="property" id="property-' + prop_esc + '" name="properties[]" value="' + prop_esc + '"><label for="property-' + prop_esc + '"><code>' + prop_esc + '</code>' + aside + '</label>';
		},

		post_file: function() {

			if (! upload_is_ready ||
			    ! properties_are_ready) {
				return;
			}

			if (is_collection) {
				var crumb = $form.data("crumb-upload-collection");
				var api_method = 'wof.upload_collection';
			} else {
				var crumb = $form.data("crumb-upload-feature");
				var api_method = 'wof.upload_feature';
			}

			var onsuccess = function(rsp) {
				self.show_result(rsp);
				mapzen.whosonfirst.log.debug(rsp);
			};
			var onerror = function(rsp) {
				self.show_result(rsp);
				mapzen.whosonfirst.log.error(rsp);
			};

			// Make sure we have a geojson_file reference set up
			if (! geojson_file) {
				mapzen.whosonfirst.log.error('No geojson_file to post.');
				return;
			}

			// Assemble our form data and send it along to the API method
			var data = new FormData();
			data.append('crumb', crumb);
			data.append('upload_file', geojson_file);

			var empty = true;

			if ($('#upload-geometry').get(0).checked) {
				data.append('geometry', 1);
				empty = false;
			}
			$('#upload-properties input').each(function(i, prop) {
				if ($(prop).attr('name') == 'properties[]' &&
				    prop.checked) {
					var name = $(prop).attr('name');
					var value = $(prop).val();
					data.append(name, value);
					empty = false;
				}
			});

			if (empty) {
				$result.html("You haven't selected anything to update.");
				return;
			}

			mapzen.whosonfirst.boundaryissues.api.api_call(api_method, data, onsuccess, onerror);

			// Show some user feedback
			$result.html('Uploading...');
		},

		show_result: function(rsp) {
			var esc_html = mapzen.whosonfirst.php.htmlspecialchars;
			if (rsp.ok) {
				var links = [];
				$.each(rsp.saved_wof, function(id, name) {
					var esc_id = esc_html(id);
					var esc_name = esc_html(name);

					var url = '/id/' + esc_id;
					url = mapzen.whosonfirst.boundaryissues.utils.abs_root_urlify(url);

					links.push('<a href="' + url + '"><code>' + esc_id + '</code> ' + esc_name + '</a>');
				});
				var list = '<ul><li>' + links.join('</li><li>') + '</li></ul>';
				$result.html('Success! ' + list);
				mapzen.whosonfirst.log.debug(rsp);
			} else if (rsp.error && rsp.error.message) {
				$result.html('Error: ' + rsp.error.message);
				mapzen.whosonfirst.log.error(rsp.error.message);
			} else {
				$result.html('Oh noes, an error! Check the JavaScript console?');
				mapzen.whosonfirst.log.error(rsp);
			}
		}

	};

	$(document).ready(function(){

		VenueIcon = L.Icon.extend({
			options: {
				iconUrl: mapzen.whosonfirst.boundaryissues.utils.abs_root_urlify('/images/marker-icon.png'),
				iconRetinaUrl: mapzen.whosonfirst.boundaryissues.utils.abs_root_urlify('/images/marker-icon-2x.png'),
				shadowUrl: null,
				iconAnchor: new L.Point(13, 42),
				iconSize: new L.Point(25, 42),
				popupAnchor: new L.Point(0, -42)
			}
		});

		poi_icon_base = mapzen.whosonfirst.boundaryissues.utils.abs_root_urlify('/images/categories/');

		self.setup_upload();
		self.setup_properties_lookup();
	});

	return self;
})();
