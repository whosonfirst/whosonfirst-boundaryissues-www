var mapzen = mapzen || {};
mapzen.whosonfirst = mapzen.whosonfirst || {};
mapzen.whosonfirst.boundaryissues = mapzen.whosonfirst.boundaryissues || {};

// for the time being this assumes jQuery is present
// that decision may change some day but not today
// (20160121/thisisaaronland)

mapzen.whosonfirst.boundaryissues.edit = (function() {

	var map,
	    marker,
	    $status = $('#edit-status'),
			nearby = {};

	var VenueIcon = L.Icon.extend({
		options: {
			iconUrl: '/images/marker-icon.png',
			iconRetinaUrl: '/images/marker-icon-2x.png',
			shadowUrl: null,
			iconAnchor: new L.Point(13, 42),
			iconSize: new L.Point(25, 42),
			popupAnchor: new L.Point(0, -42)
		}
	});

	var marker_style = {
    "color": "#000",
    "weight": 1,
    "opacity": 1,
    "radius": 4,
    "fillColor": "#d4645c",
    "fillOpacity": 0.5
  };

	var marker_hover_style = {
    "color": "#000",
    "weight": 2,
    "opacity": 1,
    "radius": 6,
    "fillColor": "#d4645c",
    "fillOpacity": 1
	};

	var esc_str = mapzen.whosonfirst.php.htmlspecialchars;
	var esc_int = parseInt;
	var esc_float = parseFloat;

	var self = {

		setup_map: function() {
			mapzen.whosonfirst.leaflet.tangram.scenefile('/tangram/refill.yaml');
			var $latInput = $('input[name="properties.geom:latitude"]');
			var $lngInput = $('input[name="properties.geom:longitude"]');
			if ($latInput.val() &&
			    $lngInput.val()) {
				var lat = parseFloat($latInput.val());
				var lng = parseFloat($lngInput.val());
				var zoom = 16;
				map = mapzen.whosonfirst.leaflet.tangram.map_with_latlon(
					'map',
					lat, lng, zoom
				);
				marker = new L.Marker([lat, lng], {
					icon: new VenueIcon(),
					draggable: true
				}).addTo(map);
				marker.on('dragend', function(e) {
					var ll = marker.getLatLng();
					self.update_coordinates(ll);
				});
				self.update_where(lat, lng);
			} else {
				// TODO: pick different lat/lng, perhaps using https://github.com/whosonfirst/whosonfirst-www-iplookup
				var lat = 40.73581157695217;
				var lon = -73.9815902709961;
				var zoom = 12;
				map = mapzen.whosonfirst.leaflet.tangram.map_with_latlon(
					'map',
					lat, lon, zoom
				);
			}
			L.control.geocoder('search-o3YYmTI', {
				markers: {
					icon: new VenueIcon()
				}
			}).addTo(map);

			self.show_nearby_results();
			map.on('dragend', function() {
				self.show_nearby_results();
			});

			self.map = map;
		},

		setup_drawing: function() {
			var drawControl = new L.Control.Draw({
				draw: {
					polyline: false,
					polygon: false,
					rectangle: false,
					circle: false,
					marker: {
						icon: new VenueIcon()
					}
				},
				edit: false
			});
			map.addControl(drawControl);

			map.on('draw:drawstart', function(e){
				if (marker){
					map.removeLayer(marker);
					marker = null;
				}
				self.reset_coordinates();
			});

			map.on('draw:created', function(e){
				marker = e.layer;
				map.addLayer(marker);
				self.update_coordinates(marker.getLatLng());
				marker.dragging.enable();
				marker.on('dragend', function(e) {
					var ll = e.target.getLatLng();
					self.update_coordinates(ll);
				});
			});
		},

		setup_properties: function() {

			$('.json-schema-object tr').each(function(i, row) {
				if ($(row).hasClass('add-row')) {
					return;
				}
				$(row).find('> th').append('<span class="remove-row">&times;</span>');
				$(row).find('.remove-row').click(function(e) {
					$(row).remove();
				});
			});

			// Add new properties to an object by changing the 'Value' field
			$('input.add-value').change(function(e) {
				if ($(e.target).val()) {
					var $rel = $(e.target).closest('.json-schema-object');
					var $row = $rel.find('> table > tbody > .add-row');
					var $key = $row.find('.add-key');
					var $value = $row.find('.add-value');
					var key = $key.val();
					var value = $value.val();
					self.add_object_row($rel, key, value);
					$key.val('');
					$value.val('');

					// Focus the 'Key' field to make multiple additions easier
					$key.focus();
				}
			});

			// Add new properties to an array by changing the 'Add an item' field
			$('input.add-item').change(function(e) {
				var $item = $(e.target);
				if ($item.val()) {
					var $rel = $(e.target).closest('.json-schema-array');
					var value = $item.val();
					self.add_array_item($rel, value);
					$item.val('');
					setTimeout(function() {
						$item.focus();
					}, 0);
				}
			});
		},

		setup_form: function() {
			$('#edit-form').submit(function(e) {
				e.preventDefault();

				var lat = $('input[name="properties.geom:latitude"]').val();
				var lng = $('input[name="properties.geom:longitude"]').val();
				var wof_name = $('input[name="properties.wof:name"]').val();

				if (! lat || ! lng) {
					$status.html('Please set geom:latitude and geom:longitude.');
				} else if (! wof_name) {
					$status.html('Please set wof:name.');
				} else {
					self.save_to_server(self.generate_geojson());
				}
			});
		},

		add_object_row: function($rel, key, value) {
			var $row = $rel.find('> table > tbody > .add-row');
			var context = $rel.data('context');
			var remove = '<span class="remove-row">&times;</span>';
			var $newRow = $(
				'<tr>' +
					'<th>' + key + remove + '</th>' +
					'<td><input name="' + context + '.' + key + '" class="property"></td>' +
				'</tr>'
			).insertBefore($row);
			$newRow.find('.remove-row').click(function(e) {
				$newRow.remove();
			});

			$rel.find('input[name="' + context + '.' + key + '"]').val(value);
		},

		add_array_item: function($rel, value) {
			var context = $rel.data('context');
			var remove = '<span class="remove-row">&times;</span>';
			var index = $rel.find('> ul > li').length;
			$rel.find('> ul').append(
				'<li>' +
					'<input name="' + context + '[' + index + ']" class="property"></td>' +
				'</tr>'
			);
			var $new_item = $rel.find('> ul > li').last();
			$new_item.find('.remove-row').click(function(e) {
				$new_item.remove();
			});
			$new_item.find('.property').val(value);
		},

		update_coordinates: function(ll) {
			// Round to the nearest 6 decimal places
			var lat = ll.lat.toFixed(6);
			var lng = ll.lng.toFixed(6);

			if ($('input[name="properties.geom:latitude"]').length == 0) {
				var $rel = $('#json-schema-object-geojson-properties');
				self.add_object_row($rel, 'geom:latitude', lat);
			} else {
				$('input[name="properties.geom:latitude"]').val(lat);
			}

			if ($('input[name="properties.geom:longitude"]').length == 0) {
				var $rel = $('#json-schema-object-geojson-properties');
				self.add_object_row($rel, 'geom:longitude', lng);
			} else {
				$('input[name="properties.geom:longitude"]').val(lng);
			}

			self.update_where(lat, lng);
		},

		update_where: function(lat, lng) {
			var html = 'Coordinates: <strong>' + lat + ', ' + lng + '</strong>' +
								 '<span id="where-parent"></span>';
			$('#where').html(html);

			$('#where-parent').click(function(e) {
				if ($('#where-parent').hasClass('is-breach')) {
					var id = esc_int($(e.target).data('id'));
					var name = esc_str($(e.target).html());
					var placetype = esc_str($(e.target).data('placetype'));
					$('input[name="properties.wof:parent_id"]').val(id);
					$('#where-parent').html(' in <strong>' + name + '</strong> (' + placetype + ')');
					$('#where-parent').removeClass('is-breach');
				}
			});

			self.reverse_geocode(lat, lng, function(rsp) {
				if (rsp.parents.length > 0) {
					var parents = rsp.parents;
					var curr_parent_id = $('input[name="properties.wof:parent_id"]').val();
					curr_parent_id = parseInt(curr_parent_id);
					var chosen_parent = self.get_parent_by_id(parents, curr_parent_id);

					if (parents.length == 1) {
						// Hey, alright, no breach. This is straightforward: choose the first parent
						var id = esc_int(parents[0].Id);
						var name = esc_str(parents[0].Name);
						var placetype = esc_str(parents[0].Placetype);
						var html = ' in <strong>' + name + '</strong> (' + placetype + ')';
						$('input[name="properties.wof:parent_id"]').val(id);
					} else if (chosen_parent) {
						// If the current parent ID matches one of the nearest parents, use that
						var name = esc_str(chosen_parent.Name);
						var placetype = esc_str(chosen_parent.Placetype);
						var html = ' in <strong>' + name + '</strong> (' + placetype + ')';
					} else {
						// There is more than one nearest parent. Gotta choose one!
						var parent_html = [];
						$.each(parents, function(i, parent) {
							var id = esc_int(parent.Id);
							var name = esc_str(parent.Name);
							var placetype = esc_str(parent.Placetype);
							parent_html.push('<strong data-id="' + id + '" data-placetype="' + placetype + '" title="Choose ' + name + ': ' + id + '">' + name + '</strong> (' + placetype + ')');
						});
						var html = ' in either ';
						html += parent_html.join(' or ');
						html += '<br><small class="caveat">more than one parent at this coordinate: <strong>click on a place</strong> to choose the best match</small>';
						$('#where-parent').addClass('is-breach');
					}
					$('#where-parent').html(html);
				}
			});
		},

		get_parent_by_id: function(parents, parent_id) {
			var found_parent = null;
			$.each(parents, function(i, parent) {
				if (parent.Id == parent_id) {
					found_parent = parent;
				}
			});
			return found_parent;
		},

		show_nearby_results: function() {
			var bounds = map.getBounds();
			var data = {
				lat_min: bounds._southWest.lat,
				lat_max: bounds._northEast.lat,
				lng_min: bounds._southWest.lng,
				lng_max: bounds._northEast.lng
			};
			var onsuccess = function(rsp) {
				$.each(rsp.results, function(i, item) {
					var id = this._source['wof:id'];
					if (nearby[id] || parseInt($('input[name="wof_id"]').val()) == id) {
						return;
					}
					marker = new L.circleMarker([
						item._source['geom:latitude'],
						item._source['geom:longitude']
					], marker_style).addTo(map);

					nearby[id] = marker;
					marker._source = item._source;
					marker.bindLabel(item._source['wof:name']);
					marker.on('click', function() {
						location.href = '/id/' + id + '/';
					});
					marker.on('mouseover', function() {
						this.setStyle(marker_hover_style);
					});
					marker.on('mouseout', function() {
						this.setStyle(marker_style);
					});
				});
			};
			var onerror = function() { };
			mapzen.whosonfirst.boundaryissues.api.api_call("wof.search", data, onsuccess, onerror);
		},

		generate_geojson: function() {
			var lat = $('input[name="properties.geom:latitude"]').val();
			var lng = $('input[name="properties.geom:longitude"]').val();

			if (! lat || ! lng) {
				return null;
			}
			lat = parseFloat(lat);
			lng = parseFloat(lng);

			var geojson_obj = {
				type: 'Feature',
				bbox: [lng, lat, lng, lat],
				geometry: {
					type: 'Point',
					coordinates: [lng, lat]
				},
				properties: {}
			};

			$('#edit-form').find('input.property').each(function(i, input) {
				var name = $(input).attr('name');
				var value = $(input).val();
				if ($(input).data('type') == 'number') {
					value = parseFloat(value);
				} else if ($(input).data('type') == 'integer') {
					value = parseInt(value);
				}
				self.assign_property(geojson_obj, name, value);
			});

			if ($('input[name="wof_id"]').length > 0) {
				var id = $('input[name="wof_id"]').val();
				id = parseInt(id);
				geojson_obj.id = id;
				geojson_obj.properties['wof:id'] = id;
			}

			geojson_obj.properties['geom:latitude'] = lat;
			geojson_obj.properties['geom:longitude'] = lng;

			return JSON.stringify(geojson_obj);
		},

		assign_property: function(context, name, value) {

			if (typeof name == 'string') {

				// Check if there are '.' chars in the name (object)
				var dot_match = name.match(/^([^.]+)\.(.+)/);

				// ... or if there is a square bracket pair (array)
				var bracket_match = name.match(/^([^.]+)\[(\d+)\]/);
			}

			if (dot_match) {
				// Looks like an object; recurse into the properties context
				if (!context[dot_match[1]]) {
					context[dot_match[1]] = {};
				}
				self.assign_property(context[dot_match[1]], dot_match[2], value);
			} else if (bracket_match) {
				// Looks like an array; recurse into the properties context
				if (!context[bracket_match[1]]) {
					context[bracket_match[1]] = [];
				}
				var index = parseInt(bracket_match[2]);
				self.assign_property(context[bracket_match[1]], index, value);
			} else {
				// If not, then we've reached the correct context
				context[name] = value;
			}
		},

		save_to_server: function(geojson) {

			var data = {
				crumb: $('#edit-form').data('crumb-save'),
				geojson: geojson
			};

			var onsuccess = function(rsp) {
				if (! rsp['wof_id']) {
					$status.html('Error saving GeoJSON: Bad response from server.');
				} else if ($('input[name="wof_id"]').length == 0) {
					var wof_id = parseInt(rsp['wof_id']);
					location.href = '/id/' + wof_id + '/';
				} else {
					$status.html('Saved');
				}
			};

			$status.html('Saving...');
			mapzen.whosonfirst.boundaryissues.api.api_call("wof.save", data, onsuccess, self.display_error);
		},

		save_to_github: function() {

			var data = {
				crumb: $('#edit-form').data('crumb-save'),
				step: 'to_github',
				wof_id: saved_wof_id,
				geojson: saved_geojson,
				is_new_record: saved_is_new_record
			};

			var onsuccess = function(rsp) {
				if (! rsp['url']) {
					$status.html('Error saving to GitHub: Bad response from server.');
				} else {
					self.save_to_disk();
				}
			};

			$status.html('Saving: to GitHub');
			mapzen.whosonfirst.boundaryissues.api.api_call("wof.save", data, onsuccess, self.display_error);
		},

		display_error: function(rsp) {
			mapzen.whosonfirst.log.error(rsp);
			var message = 'Argh, it\'s all gone pear-shaped! Check the JavaScript console?';
			if (rsp.error && rsp.error.message) {
				message = rsp.error.message;
			}
			if (rsp.error && rsp.error.code) {
				message = '[' + parseInt(rsp.error.code) + '] ' + message;
			}
			$status.html(message);
		},

		reverse_geocode: function(lat, lng, callback) {
			var data = {
				latitude: lat,
				longitude: lng,
				placetype: 'venue' // For now we just do venues
			};

			var onsuccess = function(rsp) {
				callback(rsp);
			};
			var onerror = function(rsp) {
				mapzen.whosonfirst.log.error('Error reverse geocoding.');
			};
			mapzen.whosonfirst.boundaryissues.api.api_call("wof.pip", data, onsuccess, onerror);
		},

		reset_coordinates: function() {
			$('#where').html('');
			$('input[name="properties.geom:latitude"]').val('');
			$('input[name="properties.geom:longitude"]').val('');
		},

		encode_geojson: function(onsuccess, onerror) {
			var data = {
				geojson: self.generate_geojson()
			};
			mapzen.whosonfirst.boundaryissues.api.api_call("wof.encode", data, onsuccess, onerror);
		}

	};

	$(document).ready(function() {
		if ($('#edit-form').length == 0) {
			return;
		}
		self.setup_map();
		self.setup_drawing();
		self.setup_properties();
		self.setup_form();
	});

	return self;
})();
