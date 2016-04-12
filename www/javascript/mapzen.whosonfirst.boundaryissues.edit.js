var mapzen = mapzen || {};
mapzen.whosonfirst = mapzen.whosonfirst || {};
mapzen.whosonfirst.boundaryissues = mapzen.whosonfirst.boundaryissues || {};

// for the time being this assumes jQuery is present
// that decision may change some day but not today
// (20160121/thisisaaronland)

mapzen.whosonfirst.boundaryissues.edit = (function() {

	var map,
	    marker,
	    $status,
	    nearby = {},
	    saving_disabled = false;

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
					self.update_coordinates(ll, true);
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
				var initial_position = L.Hash.parseHash(location.hash);
				if (initial_position) {
					// We've just deep-linked to a particular lat/lng, add a marker!
					var m = new L.marker(initial_position.center, {
						icon: new VenueIcon()
					});
					self.set_marker(m);
				}
			}
			L.control.geocoder('search-o3YYmTI', {
				markers: {
					icon: new VenueIcon()
				}
			}).addTo(map);
			var hash = new L.Hash(map);

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
				self.set_marker(e.layer);
				var ll = e.layer.getLatLng();
				self.lookup_hierarchy(ll.lat, ll.lng);
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

			$('#edit-properties input').change(function(e) {
				var property = $(e.target).attr('name');
				var value = $(e.target).val();
				$('#edit-form').trigger('propertychanged', [property, value]);
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

			if ($('#edit-form').hasClass('add-new-wof')) {
				// Only show editable fields on the add page
				$('tr.object-property input').each(function(i, input) {
					$parent = $(input).closest('tr');
					if ($parent.hasClass('property-editable')) {
						input.removeAttribute('disabled');
					} else {
						$parent.removeClass('property-visible');
					}
				});
			} else {
				// Show read-only fields if it's an edit page
				$('tr.object-property.property-editable input').each(function(i, input) {
					input.removeAttribute('disabled');
				});
			}
		},

		setup_form: function() {
			$('#edit-form').submit(function(e) {
				e.preventDefault();

				if (saving_disabled) {
					return;
				}

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

			$('#edit-form').on('propertychanged', function(e, property, value) {
				if (property == 'properties.wof:name') {
					var id = $('input[name="wof_id"]').val();
					if (!id) {
						return;
					}
					var title = value;
					var esc_title = mapzen.whosonfirst.php.htmlspecialchars(title);
					$('#wof_name').html(esc_title);
					document.title = title + ' (' + id + ') | Boundary Issues';
				}
			});

			$status = $('#edit-status');
			self.enable_saving();
		},

		setup_buttons: function() {
			$('#btn-current').click(function(e) {
				e.preventDefault();
				self.set_property('mz:is_current', 1);
				self.remove_property('edtf:deprecated');
			});

			$('#btn-deprecated').click(function(e) {
				e.preventDefault();
				self.set_property('mz:is_current', 0);
				self.set_property('edtf:deprecated', self.get_edtf_date(new Date()));
			});

			$('#btn-not-current').click(function(e) {
				e.preventDefault();
				self.set_property('mz:is_current', 0);
				self.set_property('edtf:cessation', self.get_edtf_date(new Date()));
			});

			$('#btn-funky').click(function(e) {
				e.preventDefault();
				self.set_property('mz:is_funky', 1);
			});

			$('#btn-rebuild-hierarchy').click(function(e) {
				e.preventDefault();
				$('input[name="properties.wof:parent_id"]').val(-1);
				var $latInput = $('input[name="properties.geom:latitude"]');
				var $lngInput = $('input[name="properties.geom:longitude"]');
				if ($latInput.val() &&
				    $lngInput.val()) {
					var lat = parseFloat($latInput.val());
					var lng = parseFloat($lngInput.val());
					self.lookup_hierarchy(lat, lng);
				}
			});
		},

		set_marker: function(m) {
			marker = m;
			map.addLayer(marker);
			self.update_coordinates(marker.getLatLng());
			marker.dragging.enable();
			marker.on('dragend', function(e) {
				var ll = e.target.getLatLng();
				self.update_coordinates(ll, true); // Update and reverse geocode
			});
		},

		set_property: function(property, value) {
			if ($('input[name="properties.' + property + '"]').length == 0) {
				var $rel = $('#json-schema-object-properties');
				self.add_object_row($rel, property, value);
			} else {
				$('input[name="properties.' + property + '"]').val(value);
			}
		},

		remove_property: function(property) {
			var row = $('input[name="properties.' + property + '"]').closest('tr');
			row.remove();
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

		update_coordinates: function(ll, reverse_geocode) {
			// Round to the nearest 6 decimal places
			var lat = ll.lat.toFixed(6);
			var lng = ll.lng.toFixed(6);

			if ($('input[name="properties.geom:latitude"]').length == 0) {
				var $rel = $('#json-schema-object-properties');
				self.add_object_row($rel, 'geom:latitude', lat);
			} else {
				$('input[name="properties.geom:latitude"]').val(lat);
			}

			if ($('input[name="properties.geom:longitude"]').length == 0) {
				var $rel = $('#json-schema-object-properties');
				self.add_object_row($rel, 'geom:longitude', lng);
			} else {
				$('input[name="properties.geom:longitude"]').val(lng);
			}

			if ($('input[name="properties.geom:bbox"]').length == 1) {
				var bbox = lng + ',' + lat + ',' + lng + ',' + lat;
				$('input[name="properties.geom:bbox"]').val(bbox);
			}

			self.update_where(lat, lng, reverse_geocode);
		},

		update_where: function(lat, lng, reverse_geocode) {
			var html = 'Coordinates: <strong>' + lat + ', ' + lng + '</strong>' +
								 '<span id="where-parent"></span>';
			$('#where').html(html);

			$('#where-parent').click(function(e) {
				if ($('#where-parent').hasClass('is-breach')) {
					var id = esc_int($(e.target).data('id'));
					self.set_parent({
						Id: id,
						Name: esc_str($(e.target).html()),
						Placetype: esc_str($(e.target).data('placetype'))
					});
					var hierarchy = JSON.parse($('input[name="properties.wof:hierarchy"]').val());
					self.set_hierarchy(self.get_hierarchy_by_id(hierarchy, id));
					$('#where-parent').removeClass('is-breach');
				}
			});

			if (! reverse_geocode) {
				var hierarchy = $('input[name="properties.wof:hierarchy"]').val();
				var parent = $('input[name="properties.wof:parent_id"]').val();
				if (hierarchy && hierarchy != '[]') {
					var hierarchy = JSON.parse(hierarchy);
					parent = parseInt(parent);
					if (hierarchy && parent) {
						$.each(hierarchy, function(i, h) {
							self.show_hierarchy(h);
						});
						if (parent != -1) {
							self.get_wof(parent, function(wof) {
								self.set_parent({
									Id: wof.properties['wof:id'],
									Name: wof.properties['wof:name'],
									Placetype: wof.properties['wof:placetype']
								});
							});
							return;
						}
					}
				}
			}
			self.lookup_hierarchy(lat, lng);
		},

		lookup_hierarchy: function(lat, lng) {
			self.reverse_geocode(lat, lng, function(rsp) {
				var parents = rsp.parents;
				var hierarchy = rsp.hierarchy;
				var curr_parent_id = $('input[name="properties.wof:parent_id"]').val();
				curr_parent_id = parseInt(curr_parent_id);
				var chosen_parent = self.get_parent_by_id(parents, curr_parent_id);
				var chosen_hierarchy = self.get_hierarchy_by_id(hierarchy, curr_parent_id);

				if (parents.length == 0) {
					// No options to choose from!
					self.set_parent(null);
				} else if (parents.length == 1) {
					// This is straightforward: choose the one and only parent
					self.set_parent(parents[0]);
				} else if (chosen_parent) {
					// If the current parent ID matches one of the parents, use that
					self.set_parent(chosen_parent);
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

					$('#hierarchy').html('');
					$('#parent').html('Parent: <code><small>-1</small></code>');
					$.each(hierarchy, function(i, h) {
						self.show_hierarchy(h);
					});
					$('input[name="properties.wof:hierarchy"]').val(JSON.stringify(hierarchy));
				}

				if (hierarchy.length == 0) {
					self.set_hierarchy(null);
					if ($('input[name="properties.wof:parent_id"]').val() != "-1") {
						$('#parent').append('<p class="caveat">This parent has no hierarchy</p>');
					}
				} else if (parents.length == 1 &&
					hierarchy.length == 1) {
					self.set_hierarchy(hierarchy[0]);
				} else if (chosen_hierarchy) {
					self.set_hierarchy(chosen_hierarchy);
				}
				$('#where-parent').html(html);
			});
		},

		get_wof: function(id, callback) {
			var url = mapzen.whosonfirst.data.id2abspath(id);
			var onsuccess = callback;
			var onerror = function() {
				mapzen.whosonfirst.log.debug("error loading '" + id + "' using get_wof");
			};
			mapzen.whosonfirst.net.fetch(url, onsuccess, onerror);
		},

		set_parent: function(parent) {
			if (! parent) {
				$('input[name="properties.wof:parent_id"]').val(-1);
				$('#where-parent').html(' in (unknown)');
				$('#parent').html('Parent: <code><small>-1</small></code>');
			} else {
				var id = esc_int(parent.Id);
				var name = esc_str(parent.Name);
				var placetype = esc_str(parent.Placetype);
				$('input[name="properties.wof:parent_id"]').val(id);
				$('#where-parent').html(' in <strong>' + name + '</strong> (' + placetype + ')');
				$('#parent').html('Parent: <a href="/id/' + id + '/">' + name + ' <code><small>' + id + '</small></code></a>');
			}
		},

		set_hierarchy: function(hierarchy) {
			$('#hierarchy').html('');
			if (! hierarchy) {
				$('input[name="properties.wof:hierarchy"]').val('[]');
			} else {
				$('input[name="properties.wof:hierarchy"]').val('[' + JSON.stringify(hierarchy) + ']');
				self.show_hierarchy(hierarchy);
			}
		},

		show_hierarchy: function(hierarchy) {
			var html = '<ul>';
			var labelRegex = /^(.+)_id$/;
			for (var key in hierarchy) {
				var id = esc_int(hierarchy[key]);
				var label = key;
				if (key.match(labelRegex)) {
					label = key.match(labelRegex)[1];
				}

				var root = $(document.body).data("abs-root-url");
				var href = root + '/belongsto/' + id + '/';

				html += '<li>' + label + ': <a href="' + href + '" class="hierarchy-needs-name hierarchy-' + id + '" data-id="' + id + '"><code><small>' + id + '</small></code></a></li>';
			}
			html += '</ul>';
			$('#hierarchy').append(html);
			self.get_hierarchy_names();
			$('#btn-rebuild-hierarchy').removeClass('disabled');

		},

		get_hierarchy_names: function() {
			var queue = [];
			$('.hierarchy-needs-name').each(function(i, link) {
				var id = $(link).data('id');
				if (queue.indexOf(id) == -1) {
					queue.push(id);
				}
			});
			$.each(queue, function(i, id) {
				if (id) {
					self.get_wof(id, function(wof) {
						var id = wof.properties['wof:id'];
						$('.hierarchy-' + id).html(wof.properties['wof:name'] + ' <code><small>' + id + '</small></code>');
						$('.hierarchy-' + id).removeClass('hierarchy-needs-name');
					});
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

		get_hierarchy_by_id: function(hierarchies, parent_id) {
			var found_hierarchy = null;
			$.each(hierarchies, function(i, hierarchy) {
				for (var key in hierarchy) {
					if (hierarchy[key] == parent_id) {
						found_hierarchy = hierarchy;
					}
				}
			});
			return found_hierarchy;
		},

		get_edtf_date: function(date) {
			var yyyy = date.getFullYear();
			var mm = self.leading_zero(date.getMonth() + 1);
			var dd = self.leading_zero(date.getDate());
			return yyyy + '-' + mm + '-' + dd;
		},

		get_venue_marker_style: function(props) {
			//console.log(props);
			if (props['wof:is_current'] == 1) {
				//console.log('current');
				return mapzen.whosonfirst.leaflet.styles.venue_current();
			} else if (props['edtf:cessation'] &&
			           props['edtf:cessation'] != 'uuuu') {
				//console.log('not current');
				return mapzen.whosonfirst.leaflet.styles.venue_not_current();
			} else if (props['edtf:deprecated'] &&
			           props['edtf:deprecated'] != 'uuuu') {
				//console.log('deprecated');
				return mapzen.whosonfirst.leaflet.styles.venue_deprecated();
			} else {
				//console.log('unknown');
				return mapzen.whosonfirst.leaflet.styles.venue_unknown();
			}
		},

		leading_zero: function(num) {
			num = parseInt(num);
			if (num < 10) {
				num = '0' + num;
			}
			return num;
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
				var m;
				$.each(rsp.results, function(i, item) {
					var id = this._source['wof:id'];
					if (nearby[id] || parseInt($('input[name="wof_id"]').val()) == id) {
						return;
					}

					var style = self.get_venue_marker_style(item._source);
					m = new L.circleMarker([
						item._source['geom:latitude'],
						item._source['geom:longitude']
					], style).addTo(map);

					nearby[id] = m;
					m._source = item._source;
					m._style = style;

					m.bindLabel(item._source['wof:name']);
					m.on('click', function() {
						location.href = '/id/' + id + '/';
					});
					m.on('mouseover', function() {
						this.setStyle(mapzen.whosonfirst.leaflet.styles.venue_hover());
					});
					m.on('mouseout', function() {
						this.setStyle(this._style);
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
				} else if ($(input).data('type') == 'json') {
					value = JSON.parse(value);
				}
				self.assign_property(geojson_obj, name, value);
			});

			if ($('input[name="wof_id"]').length > 0) {
				var id = $('input[name="wof_id"]').val();
				id = parseInt(id);
				geojson_obj.id = id;
				geojson_obj.properties['wof:id'] = id;
			}

			geojson_obj.properties['wof:parent_id'] = parseInt($('input[name="properties.wof:parent_id"]').val());
			geojson_obj.properties['wof:hierarchy'] = JSON.parse($('input[name="properties.wof:hierarchy"]').val());

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
				self.enable_saving();
			};

			var onerror = function(rsp) {
				self.display_error(rsp);
				self.enable_saving();
			};

			$status.html('Saving...');
			self.disable_saving();
			mapzen.whosonfirst.boundaryissues.api.api_call("wof.save", data, onsuccess, onerror);
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

		disable_saving: function() {
			saving_disabled = true;
			$('#btn-save').attr('disabled', 'disabled');
		},

		enable_saving: function() {
			saving_disabled = false;
			$('#btn-save').attr('disabled', null);
		},

		display_error: function(rsp) {
			var message = 'Argh, it\'s all gone pear-shaped! Check the JavaScript console?';
			if (rsp.error && rsp.error.message) {
				message = rsp.error.message;
			}
			if (rsp.error && rsp.error.code) {
				message = '[' + parseInt(rsp.error.code) + '] ' + message;
			}
			mapzen.whosonfirst.log.error(message);
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
		mapzen.whosonfirst.data.endpoint('https://whosonfirst.mapzen.com/data/');
		self.setup_map();
		self.setup_drawing();
		self.setup_properties();
		self.setup_form();
		self.setup_buttons();
	});

	return self;
})();
