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
	    feature_count,
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

			// Listen for updates on FeatureCollection uploads
			$(document.body).on('notification', function(e, data) {
				self.collection_update(data);
			});
		},

		setup_property_aliases: function(){
			var url = mapzen.whosonfirst.boundaryissues.utils.abs_root_urlify('/meta/property_aliases.json');
			var onsuccess = function(aliases){
				self.property_aliases = aliases;
				properties_are_ready = true;
			};
			var onerror = function(){
				mapzen.whosonfirst.log.debug("error loading property aliases.");
			};
			var cache_ttl = 60 * 60 * 1000; // one hour
			mapzen.whosonfirst.net.fetch(url, onsuccess, onerror, cache_ttl);
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

				var map = self.setup_map_preview(geojson);

				if (geojson.type == "Feature") {
					is_collection = false;
					if (map) {
						mapzen.whosonfirst.leaflet.fit_map(map, geojson);
						self.show_feature_preview(map, geojson);
					}
					self.show_props_preview(geojson);
				} else if (geojson.type == "FeatureCollection") {
					is_collection = true;
					if (map) {
						self.show_collection_preview(map, geojson);
					}
					self.show_props_preview(geojson);
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

		setup_map_preview: function(geojson) {

			if (! self.has_geometry(geojson)) {
				return null;
			}

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

			return map;
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

			var html = '';

			if (self.has_geometry(geojson)) {
				html += '<div class="headroom"><input type="checkbox" class="property" id="upload-geometry" name="geometry" value="1" checked="checked"><label for="upload-geometry">Update geometry</label></div>';
			}

			$.each(groups, function(i, group) {
				if (group != '_no_group_' &&
				    group_props[group].length == 1) {
					group_props._no_group_.push(group_props[group][0]);
					return;
				} else if (group_props[group].length == 0) {
					return;
				}
				group_props[group].sort();
				html += '<div class="property-group col-md-4 headroom">';
				var group_select = '<input type="checkbox" id="group-select-' + group + '">';
				var group_text = (group == '_no_group_') ? 'Other properties' : group + ' properties';
				var group_label = '<label for="group-select-' + group + '">' + group_text + '</label>';
				html += '<div class="group-select">' + group_select + group_label + '</div>';
				html += '<ul class="upload-properties">';
				$.each(group_props[group], function(i, item) {
					html += '<li>' + item + '</li>';
				});
				html += '</ul>';
				html += '</div>';
			});

			$preview_props.html(html);
			$preview_props.find('.group-select input').change(function(e) {
				var parent = $(e.target).closest('.property-group');
				parent.find('.upload-properties input').each(function(i, checkbox) {
					checkbox.checked = e.target.checked;
				});
			});
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
			if (self.property_aliases[prop]) {
				return self.property_aliases[prop];
			} else {
				return prop;
			}
		},

		get_property_html: function(prop, orig_prop) {
			var attrs = '';
			var hidden = '';
			var prop_esc = esc_str(prop);
			var orig_esc = esc_str(orig_prop);
			var aside = '';
			if (prop != orig_prop) {
				aside = ' <small><i>' + orig_esc + '</i></small>';
				hidden = '<input type="hidden" name="property_aliases[' + orig_esc + ']" value="' + prop_esc + '">';
			}
			if (prop == 'wof:id') {
				attrs = ' checked="checked" disabled="disabled"';
			}
			return '<input type="checkbox" class="property" id="property-' + orig_esc + '" name="properties[]" value="' + prop_esc + '"' + attrs + '><label for="property-' + orig_esc + '"><code>' + prop_esc + '</code>' + aside + '</label>' + hidden;
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

			if ($('#upload-geometry').length > 0 &&
			    $('#upload-geometry').get(0).checked) {
				data.append('geometry', 1);
				empty = false;
			}
			$('.upload-properties input').each(function(i, prop) {
				if ($(prop).attr('name') == 'properties[]' &&
				    prop.checked) {
					var name = $(prop).attr('name');
					var value = $(prop).val();
					data.append(name, value);
					if (value != 'wof:id') {
						empty = false;
					}
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
			if (rsp.ok) {
				var list = '<ul id="saved-wof"></ul>';
				if (rsp.collection_uuid) {
					var esc_uuid = esc_str(rsp.collection_uuid);
					$result.html('<span id="collection-' + esc_uuid + '">Processing features...</span> ' + list);
				} else if (rsp.feature) {
					$result.html('Success! ' + list);
					var wof_id = rsp.feature.properties['wof:id'];
					var wof_name = rsp.feature.properties['wof:name'];
					self.show_saved_wof_result(wof_id, wof_name);
					mapzen.whosonfirst.log.debug(rsp);
				}
			} else if (rsp.error && rsp.error.message) {
				$result.html('Error: ' + rsp.error.message);
				mapzen.whosonfirst.log.error(rsp.error.message);
			} else {
				$result.html('Oh noes, an error! Check the JavaScript console?');
				mapzen.whosonfirst.log.error(rsp);
			}
		},

		show_saved_wof_result: function(id, name) {
			var esc_id = esc_str(id);
			var esc_name = esc_str(name);
			var url = mapzen.whosonfirst.boundaryissues.utils.abs_root_urlify('/id/' + esc_id);
			$('#saved-wof').append('<li><a href="' + url + '"><code>' + esc_id + '</code> ' + esc_name + '</a></li>');
		},

		collection_update: function(data) {
			if (data.feature_count) {
				feature_count = parseInt(data.feature_count);
			} else if (data.wof_id && data.wof_name) {
				self.show_saved_wof_result(data.wof_id, data.wof_name);
				if (feature_count) {
					var $status = $('#collection-' + esc_str(data.collection_uuid));
					var num = $('#saved-wof li').length;
					if (num == feature_count) {
						$status.html('Finished!');
					} else {
						$status.html('Saved ' + num + ' of ' + feature_count + '...');
					}
				}
			}
		},

		has_geometry: function(geojson) {
			if (geojson.type == 'Feature') {
				return !! geojson.geometry;
			} else if (geojson.type == 'FeatureCollection') {
				var has_geometry = false;
				$.each(geojson.features, function(i, feature) {
					if (feature.geometry) {
						has_geometry = true;
					}
				});
				return has_geometry;
			} else {
				return false;
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
		self.setup_property_aliases();
	});

	return self;
})();
