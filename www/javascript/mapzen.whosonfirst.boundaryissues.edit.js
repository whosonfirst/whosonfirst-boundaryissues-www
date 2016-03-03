var mapzen = mapzen || {};
mapzen.whosonfirst = mapzen.whosonfirst || {};
mapzen.whosonfirst.boundaryissues = mapzen.whosonfirst.boundaryissues || {};

// for the time being this assumes jQuery is present
// that decision may change some day but not today
// (20160121/thisisaaronland)

mapzen.whosonfirst.boundaryissues.edit = (function() {

	var map,
	    marker,
	    $result;

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

	var self = {

		setup_map: function() {
			mapzen.whosonfirst.leaflet.tangram.scenefile('/tangram/refill.yaml');
			var $latInput = $('input[name="geojson.properties.geom:latitude"]');
			var $lngInput = $('input[name="geojson.properties.geom:longitude"]');
			if ($latInput.val() &&
			    $lngInput.val()) {
				var lat = parseFloat($latInput.val());
				var lng = parseFloat($lngInput.val());
				var zoom = 14;
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

				var onsuccess = function(rsp){
					self.show_result(rsp);
				};
				var onerror = function(rsp){
					self.show_result(rsp);
				};

				var place = self.encode_properties();
				if (!place) {
					return;
				}

				var data = {
					crumb: $(this).data("crumb-save"),
					geojson: JSON.stringify(place)
				};

				mapzen.whosonfirst.boundaryissues.api.api_call("wof.save", data, onsuccess, onerror);
				$result.html('Saving...');
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
			$rel.find('> ul').append(
				'<li>' +
					'<input name="' + context + '[]" class="property"></td>' +
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

			if ($('input[name="geojson.properties.geom:latitude"]').length == 0) {
				var $rel = $('#json-schema-object-geojson-properties');
				self.add_object_row($rel, 'geom:latitude', lat);
			} else {
				$('input[name="geojson.properties.geom:latitude"]').val(lat);
			}

			if ($('input[name="geojson.properties.geom:longitude"]').length == 0) {
				var $rel = $('#json-schema-object-geojson-properties');
				self.add_object_row($rel, 'geom:longitude', lng);
			} else {
				$('input[name="geojson.properties.geom:longitude"]').val(lng);
			}

			self.update_where(lat, lng);
		},

		update_where: function(lat, lng) {
			var html = 'Coordinates: <strong>' + lat + ', ' + lng + '</strong>' +
								 '<span id="where-parent"></span>';
			$('#where').html(html);

			self.lookup_parent_id(lat, lng, function(results) {
				try {
					var parent_id = results[0].Id;
					var html = ' in <strong>' + results[0].Name + '</strong> (' + results[0].Placetype + ')';
					$('input[name="geojson.properties.wof:parent_id"]').val(parent_id);
					$('#where-parent').html(html);
				} catch(e) {
					mapzen.whosonfirst.log.error('Error looking up parent_id.');
				}
			});
		},

		encode_properties: function() {
			var lat = $('input[name="geojson.properties.geom:latitude"]').val();
			var lng = $('input[name="geojson.properties.geom:longitude"]').val();

			if (! lat || ! lng) {
				$result.html('Please set the latitude and longitude before saving.');
				return null;
			}
			lat = parseFloat(lat);
			lng = parseFloat(lng);
			var place = {
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
				// Ignore initial 'geojson.' in name (e.g., "geojson.properties.wof:concordances.id")
				name = name.replace(/^geojson\./, '');
				self.assign_property(place, name, value);
			});

			if ($('input[name="wof_id"]').length > 0) {
				var id = $('input[name="wof_id"]').val();
				id = parseInt(id);
				place.id = id;
				place.properties['wof:id'] = id;
			}

			place.properties['geom:latitude'] = lat;
			place.properties['geom:longitude'] = lng;

			return place;
		},

		assign_property: function(context, name, value) {
			// Check if there are '.' chars in the name
			var next_step = name.match(/^([^.]+)\.(.+)/);
			if (next_step) {
				// If so, recurse into the properties context
				if (!context[next_step[1]]) {
					context[next_step[1]] = {};
				}
				self.assign_property(context[next_step[1]], next_step[2], value);
			} else {
				// If not, then we've reached the correct context
				context[name] = value;
			}
		},

		show_result: function(rsp) {

			if (! rsp){
				$result.html('Argh, it\'s all gone pear-shaped! Check the JavaScript console?');
				mapzen.whosonfirst.log.error(rsp);
			} else if (rsp.ok && rsp.stat == 'ok') {
				if ($('input[name="wof_id"]').length > 0) {
					var wof_name = $('input[name="geojson.properties.wof:name"]').val();
					$('#wof_name').text(wof_name);
					$result.html('Saved! <p class="caveat"><strong>Temporary duct tape</strong>: waiting 10 seconds for `git pull` to finish...</p>');
					setTimeout(function() {
						$result.html('');
					}, 10000);
				} else {
					$result.html('Saved! <p class="caveat"><strong>Temporary duct tape</strong>: waiting 10 seconds for `git pull` to finish...</p>');
					setTimeout(function() {
						location.href = '/id/' + rsp.id + '/';
					}, 10000);
				}
			} else if (rsp.error_msg) {
				$result.html('Error: ' + rsp.error_msg);
				mapzen.whosonfirst.log.error(rsp);
			} else {
				$result.html('Oh noes, an error! Check the JavaScript console?');
				mapzen.whosonfirst.log.error(rsp);
			}
		},

		lookup_parent_id: function(lat, lng, callback) {
			var data = {
				latitude: lat,
				longitude: lng
			};

			var onsuccess = function(rsp) {
				callback(rsp.results);
			};
			var onerror = function(rsp) {
				mapzen.whosonfirst.log.error('Error looking up parent_id.');
			};
			mapzen.whosonfirst.boundaryissues.api.api_call("wof.pip", data, onsuccess, onerror);
		},

		reset_coordinates: function() {
			$('#where').html('');
			$('input[name="geojson.properties.geom:latitude"]').val('');
			$('input[name="geojson.properties.geom:longitude"]').val('');
		},

		encode_geojson: function(onsuccess, onerror) {
			var data = {
				geojson: JSON.stringify(self.encode_properties())
			};
			mapzen.whosonfirst.boundaryissues.api.api_call("wof.encode", data, onsuccess, onerror);
		}

	};

	$(document).ready(function() {
		$result = $('#result');
		self.setup_map();
		self.setup_drawing();
		self.setup_properties();
		self.setup_form();
	});

	return self;
})();
