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
	    saving_disabled = false,
	    VenueIcon,
	    poi_icon_base,
	    parent_layer,
	    parent_hover,
	    geometry_layer,
	    centroid_layers;

	var esc_str = mapzen.whosonfirst.php.htmlspecialchars;
	var esc_int = parseInt;
	var esc_float = parseFloat;

	// Return null (okay) or a string (error)
	var validations = [
		function(feature) {
			if (! feature.geometry) {
				return 'This record has no geometry.';
			}
			return null;
		},
		function(feature) {
			if (! feature.properties['wof:parent_id']) {
				return 'This record has no wof:parent_id.';
			}
			return null;
		},
		function(feature) {
			var lat = feature.properties['geom:latitude'];
			var lng = feature.properties['geom:longitude'];
			if (! lat || ! lng) {
				return 'Please set <span class=\"hey-look\">geom:latitude</span> and <span class=\"hey-look\">geom:longitude</span>.';
			}
			return null;
		},
		function(feature) {
			var wof_name = feature.properties['wof:name'];
			if (! wof_name) {
				return 'Please set <span class=\"hey-look\">wof:name</span>.';
			}
			return null;
		},
		function() {
			var categories = [];
			var index = 0;
			while ($('input[name="properties.mz:categories[' + index + ']"]').length > 0) {
				var category = $('input[name="properties.mz:categories[' + index + ']"]').val();
				var match = category.match(/([a-z0-9-_]+):([a-z0-9-_]+)=.+/i);
				if (! match) {
					return "<span class=\"hey-look\">mz:categories</span> value <code>" + category + "</code> does not conform to the pattern <code>namespace:predicate=value</code>."
				}

				// Something something check namespace/predicate

				index++;
			}
			return null;
		},
		function(){
			if ($('input[name="wof_id"]').length == 0 ||
			    $('#hours').length == 0){
				return null;
			}
			var hours = self.get_hours();
			var errors = [];
			$.each(hours, function(day, openclose){
				var open = openclose.open.match(/^(\d\d):(\d\d)$/);
				var close = openclose.close.match(/^(\d\d):(\d\d)$/);
				if (! open ||
				    parseInt(open[1]) > 23 ||
				    parseInt(open[2]) > 59){
					errors.push('Open time for ' + day);
				}
				if (! close ||
				    parseInt(close[1]) > 23 ||
				    parseInt(close[2]) > 59){
					errors.push('Close time for ' + day);
				}
			});
			if (errors.length > 0){
				return "Check to make sure you've typed in the hours correctly (e.g., 09:00 or 17:30). The following values had problems: <ul><li>" + errors.join('</li><li>') + "</li></ul>";
			} else {
				return null;
			}
		}
	];

	var self = {

		setup_map: function() {

			var scene = mapzen.whosonfirst.boundaryissues.utils.abs_root_urlify('/tangram/refill.yaml');
			mapzen.whosonfirst.leaflet.tangram.scenefile(scene);

			// For now we just assume everyone using BI can read English. This
			// should be configurable. (20170704/dphiffer)
			mapzen.whosonfirst.leaflet.tangram.scene_options({
				ux_language: 'en'
			});

			mapzen.whosonfirst.leaflet.tangram.map_with_prefs('map', 'map_prefs', function(_map, prefs) {

				map = _map;
				self.map = _map;

				var placetype = $('input[name="properties.wof:placetype"]').val();
				if (placetype == 'venue') {
					self.setup_map_marker();
				} else {
					self.setup_map_geometry();
				}

				// sudo move me to stack.json
				// (20170616/dphiffer)
				var geocoder = L.control.geocoder('mapzen-LhT76h5', {
					markers: {
						icon: new VenueIcon()
					}
				}).addTo(map);

				if ($('input[name="wof_id"]').length == 0) {
					// On the "add" page, pick a good default bbox
					mapzen.whosonfirst.boundaryissues.bbox.init(map);
				}

				var hash = new L.Hash(map);

				//self.show_nearby_results();
				map.on('dragend', function() {
					//self.show_nearby_results();
				});

				slippymap.crosshairs.init(map);
				//mapzen.whosonfirst.nearby.init(map);
				//mapzen.whosonfirst.nearby.inflate_nearby();

				geocoder.on('select', function(e) {
					var html = '<a href="#" class="btn btn-primary" id="geocoder-marker-select">Use this result</a> <a href="#" class="btn" id="geocoder-marker-cancel">Cancel</a>';
					var popup = geocoder.marker.bindPopup(html).openPopup();
					var props = e.feature.properties;
					$('#geocoder-marker-select').click(function(e) {
						e.preventDefault();
						popup.closePopup();
						geocoder.collapse();
						var ll = geocoder.marker.getLatLng();
						self.lookup_hierarchy(ll.lat, ll.lng);
						self.update_coordinates(ll, true);
						self.set_marker(geocoder.marker);
						if (props.label) {
							self.set_property('addr:full', props.label);
						}
						if (props.housenumber) {
							self.set_property('addr:housenumber', props.housenumber);
						}
						if (props.street) {
							self.set_property('addr:street', props.street);
						}
						if (props.postalcode) { // Seeing postAL code coming from Pelias, but we prefer 'postcode'
							self.set_property('addr:postcode', props.postalcode);
						}
						if (props.postcode) {
							self.set_property('addr:postcode', props.postcode);
						}
					});
					$('#geocoder-marker-cancel').click(function(e) {
						e.preventDefault();
						popup.closePopup();
						map.removeLayer(geocoder.marker);
					});
				});

				self.setup_drawing();
			});
		},

		setup_map_marker: function() {
			var centroid = self.get_property_centroid();
			if (centroid) {
				var lat = centroid.lat;
				var lng = centroid.lng;
				var zoom = 16;

				map.setView([lat, lng], zoom);
				marker = new L.Marker([lat, lng], {
					icon: new VenueIcon(),
					draggable: true
				}).addTo(map);
				marker.on('dragend', function(e) {
					var ll = marker.getLatLng();
					self.update_coordinates(ll, true);
					self.disable_controlled('wof:parent_id');
					self.disable_controlled('wof:hierarchy');
				});
				self.update_where(lat, lng);

			} else {
				// TODO: pick different lat/lng, perhaps using https://github.com/whosonfirst/whosonfirst-www-iplookup
				var lat = 40.73581157695217;
				var lng = -73.9815902709961;
				var zoom = 12;
				map.setView([lat, lng], zoom);
				var initial_position = L.Hash.parseHash(location.hash);
				if (initial_position) {
					// We've just deep-linked to a particular lat/lng, add a marker!
					var m = new L.marker(initial_position.center, {
						icon: new VenueIcon()
					});
					self.set_marker(m);
					var ll = m.getLatLng();
					self.update_coordinates(ll, true);
					self.mark_changed_property('properties.geom:latitude');
					self.mark_changed_property('properties.geom:longitude');
				}
			}
		},

		setup_map_geometry: function() {
			// TODO: pick different lat/lng, perhaps using https://github.com/whosonfirst/whosonfirst-www-iplookup
			var lat = 40.73581157695217;
			var lng = -73.9815902709961;
			var zoom = 12;
			map.setView([lat, lng], zoom);
			var geojson_url = $('#geojson-link').attr('href');
			$.get(geojson_url, function(feature) {
				var bbox_style = mapzen.whosonfirst.leaflet.styles.bbox();
				var poly_style = mapzen.whosonfirst.leaflet.styles.consensus_polygon();
				mapzen.whosonfirst.leaflet.fit_map(map, feature);
				mapzen.whosonfirst.leaflet.draw_bbox(map, feature, bbox_style);
				geometry_layer = mapzen.whosonfirst.leaflet.draw_poly(map, feature, poly_style);
				centroid_layers = mapzen.whosonfirst.leaflet.draw_centroids(map, feature);
			});
			var centroid = self.get_property_centroid();
			if (centroid){
				self.update_where(centroid.lat, centroid.lng);
			}
		},

		setup_drawing: function() {

			var placetype = $('input[name="properties.wof:placetype"]').val();
			if (placetype != 'venue') {
				return;
			}

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
				self.disable_controlled('wof:parent_id');
				self.disable_controlled('wof:hierarchy');
			});

			map.on('draw:created', function(e){
				self.set_marker(e.layer);
				var ll = e.layer.getLatLng();
				self.lookup_hierarchy(ll.lat, ll.lng);
			});
		},

		setup_properties: function() {

			self.setup_property_schema();
			self.group_properties();
			self.setup_add_property();

			$('.json-schema-object > table > tbody > tr').each(function(i, row) {
				self.setup_object_row(row);
			});

			$('.json-schema-array > ul > li').each(function(i, row) {
				self.setup_array_row(row);
			});

			$('#edit-properties input').change(function(e) {
				var property = $(e.target).attr('name');
				var value = $(e.target).val();
				$('#edit-form').trigger('propertychanged', [property, value]);
			});

			$('input.add-key').change(function(e) {
				var $row = $(e.target).closest('.add-row');
				var key = $(e.target).val();
				$('#edit-form').trigger('addkey', [$row, key]);
			});

			// Add new properties to an object by changing the 'Value' field
			$('.btn-add-value').click(function(e) {
				e.preventDefault();
				if ($(e.target).closest('#property-add').length > 0){
					var $rel = $('#property-add');
				} else {
					var $rel = $(e.target).closest('.json-schema-object');
				}
				var $row = $rel.find('> table > tbody > .add-row');
				var $key = $row.find('.add-key');
				var $value = $row.find('.add-value');
				var key = $key.val();
				var value = $value.val();

				if ($rel.attr('id') == 'property-add'){
					var msg = 'Your property has been added below. Presently it’s all just appended to the end. Eventually we’ll insert it in sorted order.';
					var error_msg = 'Oops, you should choose a property name like this: <code>wof:propertyname</code>';
					var prefix = key.match(/^([a-z0-9_]+):/);
					if (! prefix){
						msg = error_msg;
					} else {
						$rel = self.get_property_rel(prefix[1]);
					}
					if ($('#property-add .caveat').length == 0) {
						$('#property-add').append('<p class="caveat">' + msg + '</p>');
					} else {
						$('#property-add .caveat').html(msg);
					}
					if (! prefix){
						return false;
					}
				}

				if (key && value) {
					self.add_object_row($rel, key, value);
					$key.val('');
					$value.val('');

					// Focus the 'Key' field to make multiple additions easier
					$key.focus();
				}
			});

			// Add new properties to an array by changing the 'Add an item' field
			$('.btn-add-item').click(function(e) {
				e.preventDefault();
				var $rel = $(e.target).closest('.json-schema-array');
				var $item = $rel.find('.add-item');
				var value = $item.val();
				if (value) {
					self.add_array_item($rel, value);
					$item.val('');
					setTimeout(function() {
						$item.focus();
					}, 0);
				}
			});

			if ($('#edit-form').hasClass('add-new-wof')) {
				$('input[name="properties.wof:id"').closest('.object-property').removeClass('property-visible');
			}
			// Show read-only fields if it's an edit page
			$('tr.object-property.property-editable input').each(function(i, input) {
				if (self.user_can_edit()) {
					input.removeAttribute('readonly');
				}
			});

			if (! self.user_can_edit()) {
				$('input[readonly="readonly"]').focus(function(e) {
					$('.editing-disabled-notice').removeClass('editing-disabled-notice');
					$div = $(e.target).closest('.json-schema-field');
					$div.addClass('editing-disabled-notice');
				});
			}

			self.setup_categories();
			self.setup_hours();
			self.setup_address();
			self.setup_names();
			self.setup_geometry();

			self.initial_wof_value = self.generate_feature();
			if (! $('#edit-form').hasClass('add-new-wof')) {
				var id = $('input[name="properties.wof:id"').val();
				self.get_wof(id, function(wof) {
					self.initial_wof_value = wof;
				});
			}
		},

		setup_array_row: function(row) {
			if (  $(row).hasClass('add-row') ||
			    ! $(row).closest('.object-property').hasClass('property-editable') ||
			    ! self.user_can_edit()) {
				return;
			}
			$(row).find('> .json-schema-field').append('<button class="btn btn-remove-item">-</button>');
			$(row).find('.btn-remove-item').click(function(e) {
				var $parent = $(row).closest('.json-schema-array');
				$(row).remove();
				var name = $parent.data('context');
				self.mark_changed_property(name);

				// Re-number the property inputs
				$parent.find('input.property').each(function(i, input) {
					var property = name + '[' + i + ']';
					var value = $(input).val();
					$(input).attr('name', property);
					$('#edit-form').trigger('propertychanged', [property, value]);
				});
			});
		},

		setup_object_row: function(row) {
			if ($(row).hasClass('add-row')) {
				// Don't need to remove the placeholder input rows
				return;
			}
			if (! $(row).hasClass('property-deletable') ||
			    ! $(row).hasClass('property-editable') ||
			    ! self.user_can_edit()) {
				return;
			}
			$(row).find('> td > .json-schema-field').append('<button class="btn btn-remove-item">-</button>');
			$(row).find('.btn-remove-item').click(function(e) {
				var name = $(row).closest('.json-schema-object').data('context');
				$(row).remove();
				self.mark_changed_property(name);
				//$('#edit-form').trigger('propertychanged', [name, null]);
			});
		},

		setup_property_schema: function() {

			var onsuccess = function(rsp) {
				self.property_schema = rsp;
			};

			var onerror = function() {
				mapzen.whosonfirst.log.error("could not load property schema");
			}

			var url = mapzen.whosonfirst.boundaryissues.utils.abs_root_urlify('/meta/whosonfirst.schema');
			var args = {
				cache_ttl: 3 * 60 * 60 * 1000 // 3 hours
			};
			mapzen.whosonfirst.net.fetch(url, onsuccess, onerror, args);
		},

		setup_add_property: function(){
			var $row = $('#property-group-minimum_viable > table > tbody > tr.add-row');
			var html = '<div id="property-add" class="json-schema-object"><h4>Add a new property</h4><table><tbody></tbody></div>';
			$('#property-group-minimum_viable').after($(html));
			$('#property-add > table > tbody').append($row);
		},

		setup_form: function() {
			$('#edit-form').submit(function(e) {
				e.preventDefault();

				if (saving_disabled) {
					return;
				}

				var feature = self.generate_feature();
				var errors = [];
				$.each(validations, function(i, validate) {
					var result = validate(feature);
					if (result) {
						errors.push(result);
					}
				});
				if (errors.length > 0) {
					if (errors.length == 1) {
						$status.html('<div class="alert alert-danger">Just one thing before you can save that: ' + errors[0] + '</div>');
					} else {
						$status.html('<div class="alert alert-danger">There are some problems you need to fix first:<ul><li>' + errors.join('</li><li>') + '</li></ul></div>');
					}
				} else {
					self.save_to_server(self.generate_geojson());
				}
			});

			$('#edit-form').on('propertychanged', function(e, property, value) {
				console.log('propertychanged', property, value);
				if (property == 'properties.wof:name') {
					var id = $('input[name="wof_id"]').val();
					if (id){
						var title = value;
						var esc_title = mapzen.whosonfirst.php.htmlspecialchars(title);
						$('#wof_name').html(esc_title);
						document.title = title + ' (' + id + ') | Boundary Issues';
					}
				} else if (property == 'properties.wof:category') {
					var category = $('select[name="properties.wof:category"]').val();
					self.set_marker_icon(category);
				}
				// Account for minimal-viable property aliases
				if ($('#property-group-minimum_viable input[name="' + property + '"]').length > 0) {
					$('input[name="' + property + '"]').each(function(i, input){
						if ($(input).val() != value){
							$(input).val(value);
						}
					});
				}
				if (property.substr(0, 25) == 'properties.wof:controlled') {
					if (self.is_controlled('wof:hierarchy')) {
						$('#hierarchy').addClass('controlled');
						$('#btn-rebuild-hierarchy').addClass('disabled');
					} else {
						$('#hierarchy').removeClass('controlled');
						$('#btn-rebuild-hierarchy').removeClass('disabled');
					}
				}
				self.mark_changed_property(property);
			});

			$('#edit-form').on('addkey', function(e, $row, key) {
				if (key == 'wof:category') {
					$row.find('input.add-value').css('display', 'none');
					var $td = $row.find('input.add-value').closest('td').append(self.category_select_html);
					var $select = $td.find('select');
					$select.focus();
					$select.change(function(e) {
						var $rel = $row.closest('.json-schema-object');
						var value = $select.val();
						self.add_object_row($rel, key, value);
						$select.remove();
						$row.find('input.add-key').val('');
						$row.find('input.add-value').val('');
						$row.find('input.add-value').css('display', 'inline');
						$row.find('input.add-key').focus();
						$('#edit-form').trigger('propertychanged', ['properties.wof:category', value]);
					});
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
				if ($('#btn-rebuild-hierarchy').hasClass('disabled') ||
				    self.is_controlled('wof:hierarchy')) {
					return;
				}
				$('#hierarchy').html('<div class="headroom">Rebuilding hierarchy...</div>');
				var centroid = self.get_property_centroid();
				if (centroid) {
					var lat = centroid.lat;
					var lng = centroid.lng;
				} else if (marker) {
					var lat = marker.getLatLng().lat;
					var lng = marker.getLatLng().lng;
				}
				self.lookup_hierarchy(lat, lng);
			});
		},

		setup_categories: function() {

			var placetype = $('input[name="properties.wof:placetype"]').val();
			if (placetype != 'venue') {
				$('#categories').prev('h3').remove();
				$('#categories').remove();
				return;
			}

			var url = mapzen.whosonfirst.boundaryissues.utils.abs_root_urlify('/meta/categories.json');

			var onsuccess = function(categories) {
				self.categories = categories;
				self.categories.uri = {};
				self.setup_categories_uris('namespace');
				self.setup_categories_uris('predicate');
				self.setup_categories_uris('value');
				self.setup_categories_uris('detail');
				self.setup_categories_ui();
			};

			var onerror = function() {
				mapzen.whosonfirst.log.error("could not load categories json");
			};

			mapzen.whosonfirst.net.fetch(url, onsuccess, onerror);
		},

		setup_categories_uris: function(type) {
			self.categories.uri[type] = {};
			$.each(self.categories[type], function(id, cat) {
				self.categories.uri[type][cat.uri] = id;
			});
		},

		setup_categories_ui: function() {
			var i = 0;
			var tags = [];
			while ($('input[name="properties.mz:categories[' + i + ']"]').length > 0) {
				tags.push($('input[name="properties.mz:categories[' + i + ']"]').val());
				i++;
			}
			if (tags.length > 0) {
				$.each(tags, function(i, tag) {
					self.assign_categories_tag(tag);
				});
			} else {
				self.append_categories_select('namespace');
			}
		},

		setup_hours: function(){
			if ($('#hours').length == 0){
				return;
			}
			var days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
			$.each(days, function(i, label){
				var day = label.toLowerCase().substr(0, 3);
				var checkbox = 'hours-checkbox-' + day;
				var openclose = 'hours-openclose-' + day;
				var open = 'hours-open-' + day;
				var close = 'hours-close-' + day;
				$('#hours').append($(
					'<div class="hours-day">' +
					'<input type="checkbox" id="' + checkbox + '" class="hours-checkbox"><label for="' + checkbox + '">' + label + '</label>' +
					'<div id="' + openclose + '" class="hours-openclose">' +
					'Open: <input type="text" id="' + open + '" placeholder="00:00"><br>' +
					'Close: <input type="text" id="' + close + '" placeholder="00:00">' +
					'</div>' +
					'</div>'
				));
			});

			$('.hours-checkbox').change(function(e){
				var $parent = $(this).closest('.hours-day');
				var $prev = $parent.prev('.hours-day');
				var $openclose = $parent.find('.hours-openclose');
				if (this.checked){
					$openclose.addClass('visible');
					if ($prev.length > 0){
						var open = $openclose.find('input')[0];
						var close = $openclose.find('input')[1];
						$(open).val($prev.find('input')[1].value);
						$(close).val($prev.find('input')[2].value);
					}
				} else {
					$openclose.removeClass('visible');
				}
			});

			if ($('input[name="properties.mz:hours"]').length > 0){
				var hours_json = $('input[name="properties.mz:hours"]').val();
				var hours = JSON.parse(hours_json);
				$.each(hours, function(day, value){
					var checkbox = '#hours-checkbox-' + day.toLowerCase();
					var openclose = '#hours-openclose-' + day.toLowerCase();
					var open = '#hours-open-' + day.toLowerCase();
					var close = '#hours-close-' + day.toLowerCase();
					if ($(checkbox).length > 0){
						$(checkbox)[0].checked = true;
						$(openclose).addClass('visible');
						$(open).val(value.open);
						$(close).val(value.close);
					}
				});
			}
		},

		setup_address: function(){
			if (! self.user_can_edit()) {
				$('#address-btn').attr('disabled', 'disabled');
				return;
			}
			$('#address-btn').attr('disabled', null);
			$('#address-btn').click(function(e) {

				e.preventDefault();

				var data = {
					query: $('#address-query').val(),
					latitude: $('input[name="properties.geom:latitude"]').val(),
					longitude: $('input[name="properties.geom:longitude"]').val()
				};

				$('#address-btn').removeClass('btn-primary');
				$('#address-btn').html('Loading...');
				$('#address-btn').attr('disabled', 'disabled');

				var onsuccess = function(rsp){

					$('#address-btn').addClass('hidden');
					$('#address-query').addClass('hidden');

					var html = '<p><strong>Assign these properties?</strong> <span class="caveat">Note: not <strong>all</strong> address properties are encoded in addr:* fields.</span></p>';
					html += '<dl id="address-properties">';

					$.each(rsp.properties, function(key, value) {
						key = esc_str(key);
						value = esc_str(value);
						var input = '<input type="text" data-property="' + key + '" class="address-property" value="' + value + '">';
						html += '<dt>' + key + '</dt>' +
						        '<dd>' + input + '</dd>';
					});
					html += '</dl>';
					html += '<button id="address-assign" class="btn btn-primary">Assign properties</button>';
					html += ' <button id="address-cancel" class="btn">Cancel</button>';
					$('#address-results').html(html);

					var resetAddress = function(){
						$('#address-btn').removeClass('hidden');
						$('#address-query').removeClass('hidden');
						$('#address-btn').addClass('btn-primary');
						$('#address-btn').html('Extract properties');
						$('#address-btn').attr('disabled', null);
					};

					$('#address-assign').click(function(e){

						e.preventDefault();

						$('#address-properties .address-property').each(function(i, input) {
							var property = $(input).data('property');
							var value = $(input).val();
							self.set_property(property, value);
						});
						resetAddress();
						$('#address-results').html('<p class="headroom">Assigned properties!</p>');
					});

					$('#address-cancel').click(function(e){
						resetAddress();
						$('#address-results').html('');
					});
				};

				var onerror = function(rsp){
					$('#address-results').html('Error parsing address!');
					$('#address-btn').addClass('btn-primary');
					$('#address-btn').html('Extract properties');
					$('#address-btn').attr('disabled', null);
				};

				mapzen.whosonfirst.boundaryissues.api.api_call("wof.address_lookup", data, onsuccess, onerror);
			});
		},

		setup_names: function(){
			var lang = 'eng';
			$('#names-languages').val(lang)
			$('#names-language-' + lang).addClass('property-visible');
			$('#names-languages').change(function(e){
				var lang = $('#names-languages').val();
				$('.names-language.property-visible').removeClass('property-visible');
				$('#names-language-' + lang).addClass('property-visible');
			});
		},

		setup_geometry: function() {

			var placetype = $('input[name="properties.wof:placetype"]').val();
			if (placetype == 'venue'){
				$('#geometry').prev('h3').remove();
				$('#geometry').remove();
				$('#geometry-results').remove();
				return;
			}

			$('#geometry input[name=geojson_file]').on('change', function(e){
				var geojson_file = e.target.files[0];
				var reader = new FileReader();
				reader.onload = function(e){
					try {
						var geojson = JSON.parse(reader.result);
					} catch(e) {
						$('#geometry-results').html('<p class="caveat">' + e + '</p>');
						return;
					}

					var style = mapzen.whosonfirst.leaflet.styles.consensus_polygon();
					if (geojson.type == 'FeatureCollection'){
						geojson = geojson.features[0];
					}

					geometry_layer.clearLayers();
					geometry_layer = mapzen.whosonfirst.leaflet.draw_poly(map, geojson, style);
					if (centroid_layers.math_centroid) {
						centroid_layers.math_centroid.bringToFront();
					}
					if (centroid_layers.label_centroid) {
						centroid_layers.label_centroid.bringToFront();
					}

					var geometry_json = JSON.stringify(geojson.geometry);

					$('input[name="geometry"]').val(geometry_json);
					$('#geometry-results').html('<p class="caveat">Geometry updated!</p>');
					self.mark_changed_property('geometry');
				}

				// Load up the file to kick off the preview
				if (geojson_file) {
					reader.readAsText(geojson_file);
				} else {
					mapzen.whosonfirst.log.error('No geojson_file to preview.');
				}
			});
		},

		mark_changed_property: function(name) {
			if (! name) {
				return;
			}

			var orig_name = name;
			var names_input = name.match(/^names\.([^.]+)\.(.+)$/);
			if (names_input) {
				var lang = names_input[1];
				var kind = names_input[2];
				name = 'properties.name:' + lang + '_x_' + kind;
			}

			var target = $(
				'input[name="' + orig_name + '"],' +
				'.json-schema-array[data-context="' + orig_name + '"] > ul > li > input,' +
				'.json-schema-array[data-context="' + orig_name + '"]'
			).parents('tr, li');

			var wof_value = self.generate_feature();
			var property = name.replace(/^properties\./, '');
			var array_input = property.match(/^(.+)\[(\d+)\]$/);
			var object_input = property.match(/^([^.]+)\.([^.]+)$/);
			var init_value = null;
			var curr_value = null;
			if (array_input) {
				property = array_input[1];
				name = 'properties.' + property;
				if (wof_value.properties[property]) {
					var index = parseInt(array_input[2]);
					curr_value = wof_value.properties[property][index];
				}
				if (self.initial_wof_value.properties[property]) {
					init_value = self.initial_wof_value.properties[property][index];
				}
			} else if (object_input) {
				property = object_input[1];
				name = 'properties.' + property;
				if (wof_value.properties[property]) {
					var subproperty = object_input[2];
					curr_value = wof_value.properties[property][subproperty];
				}
				if (self.initial_wof_value.properties[property]) {
					init_value = self.initial_wof_value.properties[property][subproperty];
				}
			} else {
				curr_value = wof_value.properties[property];
				init_value = self.initial_wof_value.properties[property];
			}
			var $edit_list = $('#edit-status > ul');
			if (! $edit_list.length) {
				$('#edit-status').append('<div id="edit-summary"></div><ul></ul>');
				$edit_list = $('#edit-status > ul');
			}
			if (JSON.stringify(curr_value) != JSON.stringify(init_value)) {
				target.addClass('property-changed');
				if ($edit_list.find('li[data-context="' + name + '"]').length == 0) {
					$edit_list.append('<li data-context="' + name + '"><code>' + property + '</code></li>');
				}
			} else {
				target.removeClass('property-changed');
				$edit_list.find('li[data-context="' + name + '"]').remove();
			}
			if ($('#edit-status > ul > li').length == 0) {
				$('#edit-summary').html('No pending changes');
			} else {
				$('#edit-summary').html('Pending changes:');
			}
		},

		get_property_rel: function(prefix){
			$rel = $('#property-group-' + prefix);
			if ($rel.length == 0){
				var html = '<h3 id="property-group-heading-' + prefix + '" class="property-group-heading">' + prefix + '</h3>' +
					   '<div id="property-group-' + prefix + '" class="property-group json-schema-object" data-context="properties"><table><tbody></tbody></table></div>';
				$('.property-group').last().after(html);
				$rel = $('#property-group-' + prefix);
				$('#property-group-heading-' + prefix).click(self.toggle_property_group);
			}
			return $rel;
		},

		get_property_centroid: function(){
			var $lbl_lat = $('input[name="properties.lbl:latitude"]');
			var $lbl_lng = $('input[name="properties.lbl:longitude"]');
			var $geom_lat = $('input[name="properties.geom:latitude"]');
			var $geom_lng = $('input[name="properties.geom:longitude"]');

			var centroid = {};

			if ($geom_lat.val() &&
			    $geom_lng.val()){
				centroid.geom_lat = parseFloat($geom_lat.val());
				centroid.geom_lng = parseFloat($geom_lng.val());
				centroid.lat = centroid.geom_lat;
				centroid.lng = centroid.geom_lng;
			}

			if ($lbl_lat.val() &&
			    $lbl_lng.val()){
				centroid.lbl_lat = parseFloat($lbl_lat.val());
				centroid.lbl_lng = parseFloat($lbl_lng.val());
				centroid.lat = centroid.lbl_lat;
				centroid.lng = centroid.lbl_lng;
			}

			if (centroid.lat && centroid.lng){
				return centroid;
			} else {
				return null;
			}
		},

		group_properties: function(){
			var groups = {};
			$('#json-schema-object-properties > table > tbody > tr > th').each(function(i, th){
				var property = $(th).html().trim();
				var prefix = property.match(/^[a-z0-9_]+/);
				if (! prefix){
					return;
				}
				var $row = $(th).closest('tr');
				if (! groups[prefix]){
					groups[prefix] = [];
				}
				groups[prefix].push($row);
			});
			var prefixes = Object.keys(groups).sort(function(a, b){
				// Sort by prefix, but keep wof at the top.
				if (a == 'wof'){
					return 1;
				} else if (b == 'wof'){
					return -1;
				} else {
					return (a < b) ? 1 : -1;
				}
			});
			$.each(prefixes, function(i, prefix){
				var html = '<h3 class="property-group-heading collapsed">' + prefix + '</h3>' +
				           '<div id="property-group-' + prefix + '" class="property-group collapsed json-schema-object" data-context="properties"><table><tbody></tbody></table></div>';
				$('#json-schema-object-properties').after(html);
				if (prefix == 'name') {
					self.setup_name_properties(groups[prefix]);
				} else {
					$.each(groups[prefix], function(j, $row){
						$('#property-group-' + prefix + ' > table > tbody').append($row);
						if ($row.hasClass('property-minimum_viable')){
							$('#json-schema-object-properties > table > tbody').append($row.clone());
						}
					});
				}
			});

			$mvp = $('#json-schema-object-properties');
			$mvp.attr('id', 'property-group-minimum_viable');
			$mvp.addClass('property-group');
			$('#edit-properties > h3').remove();
			$mvp.before('<h4 id="mvp-heading" class="property-group-heading">Minimum viable properties</h4>');

			$('.property-group-heading').click(self.toggle_property_group);
		},

		setup_name_properties: function(props){
			$.each(props, function(j, $row){
				$row.remove();
			});
			$('#json-schema-object-names > table > tbody > tr > th').each(function(i, th){
				var lang = $(th).html();
				$(th).closest('tr').attr('id', 'names-language-' + lang);
				$(th).closest('tr').addClass('names-language');
				$(th).closest('tr').removeClass('property-visible');
			});
			var $langRows = $('#json-schema-object-names > table > tbody > tr');
			$('#property-group-name > table > tbody').append('<tr><td colspan="2" id="name-languages-holder"></td></tr>');
			$('#name-languages-holder').html($('#names-languages'));
			$('#property-group-name > table > tbody').append($langRows);
		},

		toggle_property_group: function(e){
			var $group = $(e.target).next('.property-group');
			$group.toggleClass('collapsed');
			$target = $(e.target);
			$target.toggleClass('collapsed');

			$collapsed = $('.property-group-heading.collapsed');
			$headings = $('.property-group-heading');
		},

		assign_categories_tag: function(tag) {

			var matches = tag.match(/^([^:]+):([^=]+)=(.+)$/);
			if (! matches) {
				return;
			}
			var namespace = matches[1];
			var predicate = matches[2];
			var value = matches[3];
			var namespace_id = self.categories.uri.namespace[namespace];
			if (namespace_id) {
				self.append_categories_select('namespace', namespace_id);
			}
			var predicate_id = self.categories.uri.predicate[predicate];
			if (predicate_id) {
				self.append_categories_select('predicate', predicate_id);
			}
			var value_id = self.categories.uri.value[value];
			if (value_id) {
				self.append_categories_select('value', value_id);
			}
			var detail_id = self.categories.uri.detail[value];
			if (detail_id) {
				self.append_categories_select('detail', detail_id);
			}
		},

		set_marker: function(m) {
			if (marker) {
				map.removeLayer(marker);
			}
			marker = m;
			map.addLayer(marker);
			self.update_coordinates(marker.getLatLng());
			marker.dragging.enable();
			marker.on('dragend', function(e) {
				var ll = e.target.getLatLng();
				self.update_coordinates(ll, true); // Update and reverse geocode
			});
		},

		set_marker_icon: function(icon_id) {
			if (marker) {
				var icon = L.icon({
					iconRetinaUrl: poi_icon_base + icon_id + '.png',
					iconSize: [19, 19],
					iconAnchor: [9, 9],
					popupAnchor: [-3, -38]
				});
				marker.setIcon(icon);
			}
		},

		set_property: function(property, value) {
			if (! self.user_can_edit()) {
				return;
			}
			var $array = $('.json-schema-array[data-context="properties.' + property + '"]');
			if ($array.length > 0) {
				// Clear out the items in the array, add new ones back in
				$array.find('> ul > li').remove();
				$.each(value, function(i, item) {
					self.add_array_item($array, item);
				});
				//$('#edit-form').trigger('propertychanged', ['properties.' + property, value]);
			} else if ($('input[name="properties.' + property + '"]').length == 0) {
				// Property seems not to exist, make a new one!
				var prefix = property.match(/^([a-z0-9_]+):/);
				if (prefix){
					var $rel = self.get_property_rel(prefix[1]);
				} else {
					mapzen.whosonfirst.log.error('Property ' + property + ' did not match the namespace:predicate regex.');
				}
				self.add_object_row($rel, property, value);
			} else {
				// Set the existing input's value
				if (typeof value == 'object') {
					$('input[name="properties.' + property + '"]').val(JSON.stringify(value));
				} else {
					$('input[name="properties.' + property + '"]').val(value);
				}
				$('#edit-form').trigger('propertychanged', ['properties.' + property, value]);
			}
		},

		remove_property: function(property) {
			var row = $('input[name="properties.' + property + '"]').closest('tr');
			row.remove();
		},

		add_object_row: function($rel, key, value) {
			var $addRow = $rel.find('> table > tbody > .add-row');
			var context = $rel.data('context');
			if ($('input[name="' + context + '.' + key + '"]').length > 0) {
				alert('Oops, there is already a property with that name.');
				return;
			}

			var remove = '<button class="btn btn-remove-item">-</button>';
			var type = '';

			if (self.property_schema) {
				var props = self.property_schema.allOf[1].properties.properties.properties;
				if (props[key] && props[key].type) {
					type = ' data-type="' + htmlspecialchars(props[key].type) + '"';
				}
			}

			var $newRow = $(
				'<tr class="object-property property-visible property-editable property-deletable">' +
					'<th>' + key + '</th>' +
					'<td><input type="text" name="' + context + '.' + key + '" class="property"' + type + '>' + remove + '</td>' +
				'</tr>'
			);
			if ($addRow.length) {
				$newRow.insertBefore($addRow);
			} else {
				$rel.find('> table > tbody').append($newRow);
			}

			self.setup_object_row($newRow);

			var $input = $rel.find('input[name="' + context + '.' + key + '"]');
			if (typeof value == 'object') {
				$input.val(JSON.stringify(value));
			} else {
				$input.val(value);
			}
			$('#edit-form').trigger('propertychanged', [context + '.' + key, value]);
		},

		add_array_item: function($rel, value) {
			var $prop = $rel.closest('.object-property');
			var disabled = (
				$prop.hasClass('property-editable') &&
				self.user_can_edit()
			) ? '' : ' readonly="readonly"';
			var context = $rel.data('context');
			var remove = (
				$prop.hasClass('property-editable') &&
				self.user_can_edit()
			    ) ? '<button class="btn btn-remove-item">-</button>' : '';
			var index = $rel.find('> ul > li').length;
			$rel.find('> ul').append(
				'<li>' +
					'<input name="' + context + '[' + index + ']" ' + disabled + 'type="text" class="property">' + remove +
				'</li>'
			);
			var $new_item = $rel.find('> ul > li').last();

			self.setup_array_row($new_item);

			if (typeof value == 'object') {
				$new_item.find('.property').val(JSON.stringify(value));
			} else {
				$new_item.find('.property').val(value);
			}

			$('#edit-form').trigger('propertychanged', [context + '[' + index + ']', value]);
		},

		update_coordinates: function(ll, reverse_geocode) {
			// Round to the nearest 6 decimal places
			var lat = ll.lat.toFixed(6);
			var lng = ll.lng.toFixed(6);

			if ($('input[name="properties.geom:latitude"]').length == 0) {
				var $rel = self.get_property_rel('geom');
				self.add_object_row($rel, 'geom:latitude', lat);
			} else {
				$('input[name="properties.geom:latitude"]').val(lat);
			}

			if ($('input[name="properties.geom:longitude"]').length == 0) {
				var $rel = self.get_property_rel('geom');
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
				if ($('#where-parent').hasClass('is-breach') &&
				    self.user_can_edit()) {
					var id = esc_int($(e.target).data('id'));
					self.set_parent({
						Id: id,
						Name: esc_str($(e.target).html()),
						Placetype: esc_str($(e.target).data('placetype'))
					});
					var hierarchy = JSON.parse($('input[name="properties.wof:hierarchy"]').val());
					self.set_hierarchy([self.get_hierarchy_by_id(hierarchy, id)]);
					$('#where-parent').removeClass('is-breach');
					map.removeLayer(parent_hover);
					parent_hover = null;
					self.set_controlled('wof:parent_id');
					self.set_controlled('wof:hierarchy');
				}
			});

			if (! reverse_geocode) {
				var hierarchy = $('input[name="properties.wof:hierarchy"]').val();
				var parent = $('input[name="properties.wof:parent_id"]').val();
				if (hierarchy && hierarchy != '[]') {
					var hierarchy = JSON.parse(hierarchy);
					parent = parseInt(parent);
					if (hierarchy && parent) {
						self.show_hierarchy(hierarchy);
						if (parent > 0) {
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

		append_categories_select: function(type, value, parent_id) {

			var select = '<select id="categories-' + type + '"><option value="">Choose</option>';
			var options = [];
			$.each(self.categories[type], function(id, cat) {
				if (! parent_id || cat.parent_id == parent_id) {
					cat.id = id;
					options.push(cat);
				}
			});
			if (options.length == 0) {
				return;
			}
			options.sort(function(a, b) {
				if (a.rank < b.rank) {
					return -1;
				} else {
					return 1;
				}
			});
			$.each(options, function(i, cat) {
				var selected = (cat.id == value) ? ' selected="selected"' : '';
				select += '<option value="' + cat.id + '"' + selected + '>' + cat.label + '</option>';
			});
			select += '</select>';
			$('#categories').append(select);

			var child_type = null;
			if (type == 'namespace') {
				child_type = 'predicate';
			} else if (type == 'predicate') {
				child_type = 'value';
			} else if (type == 'value') {
				child_type = 'detail';
			}

			if (child_type) {
				$('#categories-' + type).change(function() {
					if (type == 'namespace') {
						$('#categories-predicate, #categories-value, #categories-detail').remove();
					} else if (type == 'predicate') {
						$('#categories-value, #categories-detail').remove();
					} else if (type == 'value') {
						$('#categories-detail').remove();
					}
					var parent_id = $(this).val();
					if (parent_id) {
						self.append_categories_select(child_type, null, parent_id);
					}
				});
			}

			if (type == 'value' || type == 'detail') {
				$('#categories-' + type).change(function() {
					var categories = self.get_categories();
					var categories_json = JSON.stringify(categories);
					self.set_property('mz:categories', categories);
				});
			}
		},

		get_categories: function() {
			var tags = [];
			var namespace_id = $('#categories-namespace').val();
			var predicate_id = $('#categories-predicate').val();
			var value_id = $('#categories-value').val();
			if (! namespace_id ||
			    ! predicate_id ||
			    ! value_id) {
				return [];
			}
			var namespace = self.categories.namespace[namespace_id].uri;
			var predicate = self.categories.predicate[predicate_id].uri;
			var value = self.categories.value[value_id].uri;
			tags.push(namespace + ':' + predicate + '=' + value);
			if ($('#categories-detail').val()) {
				namespace = predicate;
				predicate = value;
				value_id = $('#categories-detail').val();
				value = self.categories.detail[value_id].uri;
				tags.push(namespace + ':' + predicate + '=' + value);
			}
			return tags;
		},

		get_hours: function(){
			var hours = {};
			var days = ['Sunday', 'Monday', 'Tueday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
			$.each(days, function(i, label){
				var day = label.toLowerCase().substr(0, 3);
				var checkbox = '#hours-checkbox-' + day;
				var open = '#hours-open-' + day;
				var close = '#hours-close-' + day;
				if (! $(checkbox)[0].checked){
					return;
				}
				hours[day] = {
					open: $(open).val(),
					close: $(close).val()
				};
			});
			return hours;
		},

		lookup_hierarchy: function(lat, lng) {
			self.reverse_geocode(lat, lng, function(rsp) {
				var parents = rsp.parents;
				var hierarchy = rsp.hierarchy;
				var parent_id = rsp.parent_id;
				var curr_parent_id = $('input[name="properties.wof:parent_id"]').val();
				curr_parent_id = parseInt(curr_parent_id);

				var chosen_parent = null;
				var chosen_hierarchy = null;
				if (self.is_controlled('wof:parent_id')) {
					chosen_parent = self.get_parent_by_id(parents, curr_parent_id);
					chosen_hierarchy = self.get_hierarchy_by_id(hierarchy, curr_parent_id);
				}


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
					var parent_wof = {};
					$.each(parents, function(i, parent) {
						var id = esc_int(parent.Id);
						var name = esc_str(parent.Name);
						var placetype = esc_str(parent.Placetype);
						parent_html.push('<strong class="parent-candidate" data-id="' + id + '" data-placetype="' + placetype + '" title="Choose ' + name + ': ' + id + '">' + name + '</strong> (' + placetype + ')');
						self.get_wof(id, function(wof) {
							parent_wof[id] = wof;
						});
					});
					var html = ' in either ';
					html += parent_html.join(' or ');
					if (self.user_can_edit()) {
						html += '<br><small class="caveat">more than one parent at this coordinate: <strong>click on a place</strong> to choose the best match</small>';
					}
					$('#where-parent').addClass('is-breach');

					$('#hierarchy').html('');
					$('#parent').html('Parent: <code><small>' + parent_id + '</small></code>');
					$.each(hierarchy, function(i, h) {
						self.show_hierarchy(h);
					});
					self.set_property('wof:parent_id', parent_id);

					$('#where-parent').mouseover(function(e) {
						var id = $(e.target).data('id');
						if (! id) {
							return true;
						}
						if (parent_hover) {
							map.removeLayer(parent_hover);
						}
						parent_hover = L.geoJson(parent_wof[id], {
							style: mapzen.whosonfirst.leaflet.styles.parent_polygon
						}).addTo(map);
					});

					$('#where-parent').mouseout(function() {
						if (parent_hover) {
							map.removeLayer(parent_hover);
							parent_hover = null;
						}
					});

					if (parent_layer) {
						map.removeLayer(parent_layer);
						parent_layer = null;
					}
				}

				if (hierarchy.length == 0) {
					self.set_hierarchy([]);
					var input_parent_id = $('input[name="properties.wof:parent_id"]').val();
					input_parent_id = parseInt(input_parent_id);
					if (input_parent_id < 0) {
						$('#parent').append('<p class="caveat">This parent has no hierarchy</p>');
					}
				} else if (chosen_hierarchy) {
					self.set_hierarchy([chosen_hierarchy]);
				} else {
					self.set_hierarchy(hierarchy);
				}
				$('#where-parent').html(html);
			});
		},

		get_wof: function(id, callback) {
			var url = mapzen.whosonfirst.uri.id2abspath(id);
			var onsuccess = callback;
			var onerror = function() {
				mapzen.whosonfirst.log.debug("error loading '" + id + "' using get_wof");
			};
			mapzen.whosonfirst.net.fetch(url, onsuccess, onerror);
		},

		set_parent: function(parent) {
			if (parent_layer) {
				map.removeLayer(parent_layer);
			}
			if (! parent) {
				var id = -1;
				$('#where-parent').html(' in (unknown)');
				$('#parent').html('Parent: <code><small>-1</small></code>');
			} else {
				var id = esc_int(parent.Id);
				var name = esc_str(parent.Name);
				var placetype = esc_str(parent.Placetype);
				$('#where-parent').html(' in <strong>' + name + '</strong> (' + placetype + ')');

				var url = '/id/' + id + '/';
				url = mapzen.whosonfirst.boundaryissues.utils.abs_root_urlify(url);
				$('#parent').html('Parent: <a href="' + url + '">' + name + ' <code><small>' + id + '</small></code></a>');

				self.get_wof(id, function(wof) {
					parent_layer = L.geoJson(wof, {
						style: mapzen.whosonfirst.leaflet.styles.parent_polygon
					}).addTo(map);
					parent_layer.bringToBack();
				});
			}
			self.set_property('wof:parent_id', id);
		},

		set_hierarchy: function(hierarchy) {
			$('#hierarchy').html('');
			if (! hierarchy) {
				$('input[name="properties.wof:hierarchy"]').val('[]');
			} else {
				$('input[name="properties.wof:hierarchy"]').val(JSON.stringify(hierarchy));
			}
			self.show_hierarchy(hierarchy);

			if (hierarchy.length == 1 && hierarchy[0].country_id) {
				self.set_country_properties(hierarchy[0].country_id);
			}
			self.set_property('wof:hierarchy', hierarchy);
		},

		show_hierarchy: function(hierarchy) {
			$.each(hierarchy, function(i, hierarchy_part) {
				var html = '<ul class="wof-hierarchy">';
				var labelRegex = /^(.+)_id$/;
				for (var key in hierarchy_part) {
					var id = esc_int(hierarchy_part[key]);
					var label = key;
					if (key.match(labelRegex)) {
						label = key.match(labelRegex)[1];
					}

					var url = '/id/' + id + '/';
					url = mapzen.whosonfirst.boundaryissues.utils.abs_root_urlify(url);

					html += '<li>' + label + ': <a href="' + url + '" class="wof-namify hierarchy-' + id + '" data-wof-id="' + id + '">' + id + '</a></li>';
				}
				html += '</ul>';
				$('#hierarchy').append(html);
			});

			var container = $('#hierarchy')[0];
			mapzen.whosonfirst.boundaryissues.namify.update(container);
			$('#btn-rebuild-hierarchy').removeClass('disabled');

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
			if (props['wof:is_current'] == 1) {
				return mapzen.whosonfirst.leaflet.styles.venue_current();
			} else if (props['edtf:cessation'] &&
			           props['edtf:cessation'] != 'uuuu') {
				return mapzen.whosonfirst.leaflet.styles.venue_not_current();
			} else if (props['edtf:deprecated'] &&
			           props['edtf:deprecated'] != 'uuuu') {
				return mapzen.whosonfirst.leaflet.styles.venue_deprecated();
			} else {
				return mapzen.whosonfirst.leaflet.styles.venue_unknown();
			}
		},

		set_country_properties: function(country_id) {
			var base_url = $('body').data('data-abs-root-url');
			var relpath = mapzen.whosonfirst.uri.id2relpath(country_id);
			var url = base_url + relpath;

			var on_success = function(rsp) {
				if (! rsp.properties) {
					mapzen.whosonfirst.log.error('Tried to set country properties, but the country WOF record had no properties.');
					return;
				}
				var props = rsp.properties;
				if (props['iso:country']) {
					self.set_property('iso:country', props['iso:country']);
				}
				if (props['wof:country']) {
					self.set_property('wof:country', props['wof:country']);
				}
			};

			var on_failure = function(rsp) {
				mapzen.whosonfirst.log.error('Failed to set country properties.');
			}

			mapzen.whosonfirst.net.fetch(url, on_success, on_failure);
		},

		leading_zero: function(num) {
			num = parseInt(num);
			if (num < 10) {
				num = '0' + num;
			}
			return num;
		},

		show_nearby_results: function() {

			// Disable this until we figure out why it's not working
			// (20170810/dphiffer)
			return;

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
						var url = '/id/' + id + '/';
						url = mapzen.whosonfirst.boundaryissues.utils.abs_root_urlify(url);

						location.href = url;
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
			//mapzen.whosonfirst.boundaryissues.api.api_call("wof.search", data, onsuccess, onerror);
		},

		generate_feature: function() {

			var placetype = $('input[name="properties.wof:placetype"]').val();

			if (placetype == 'venue') {
				var lat = $('input[name="properties.geom:latitude"]').val();
				var lng = $('input[name="properties.geom:longitude"]').val();

				if (! lat || ! lng) {
					var geometry = null;
					var bbox = null;
				} else {
					lat = parseFloat(lat);
					lng = parseFloat(lng);
					var geometry = {
						type: 'Point',
						coordinates: [lng, lat]
					};
					var bbox = [lng, lat, lng, lat];
				}
			} else {
				var geometry_json = $('input[name="geometry"]').val();
				var geometry = JSON.parse(geometry_json);
				var bbox = mapzen.whosonfirst.geojson.derive_bbox({
					'type': 'Feature',
					'geometry': geometry
				});
			}

			var feature = {
				type: 'Feature',
				bbox: bbox,
				geometry: geometry,
				properties: {}
			};

			$('#edit-form').find('.property, .add-item, .add-value').each(function(i, input) {
				if ($(input).closest('.names-language').length > 0){
					// Don't encode custom UI name:* properties
					return;
				}

				var parent_selectors = '.json-schema-array, .json-schema-object';
				var $parent = $(input).closest(parent_selectors);

				// This next conditional block exists to grab properties
				// that have *not yet been added* which I know sounds weird
				// but trust me here. This is for arrays and objects. When
				// you add a new item or value to an array/object, you
				// press a "+" button and it adds it to the list. But what
				// if just type in the property and do not press the "+"?
				// That's where all this comes in. (20170228/dphiffer)
				if ($(input).hasClass('add-value')) {
					if ($(input).val() == '') {
						return;
					}

					var key = $parent.find('.add-key').val();
					if (key == '') {
						// Ok, forget it, we don't have a key to work with
						return;
					}
					var context = $parent.data('context');
					var name = context + '.' + key;
				} else if ($(input).hasClass('add-item')) {
					is_new_item = true;
					if ($(input).val() == '') {
						return;
					}
					var key = '[' + $parent.find('.property').length + ']';
					var context = $parent.data('context');
					var name = context + key;
				} else {
					var name = $(input).attr('name');
				}
				var value = $(input).val();

				var type = null;
				if ($parent.data('items-type')) {
					type = $parent.data('items-type');
				}

				if (! type) {
					var type = $(input).data('type');
				}
				if (type == 'number') {
					if (typeof value == 'string') {
						value = value.replace(/[^0-9.-]/g, '');
					}
					value = parseFloat(value);
				} else if (type == 'integer') {
					if (typeof value == 'string') {
						value = value.replace(/[^0-9-]/g, '');
					}
					value = parseInt(value);
				} else if (type == 'json') {
					value = JSON.parse(value);
				}
				self.assign_property(feature, name, value);
			});

			// Some array properties are required and may not have any inputs to
			// iterate over. Encodes as [], when empty.
			$('.json-schema-required > .json-schema-array').each(function(i, prop) {
				if ($(prop).find('> ul > li').length == 0 &&
				    $(prop).find('.add-item').val() == '') {
					var name = $(prop).data('context');
					self.assign_property(feature, name, []);
				}
			});

			// I think this might only be for empty concordances dictionary,
			// at least for now. It'll encode as {} when empty.
			$('.json-schema-required > .json-schema-object').each(function(i, prop) {
				if ($(prop).find('> table > tbody > tr.object-property').length == 0 &&
				    $(prop).find('.add-key').val() == '' &&
				    $(prop).find('.add-value').val() == '') {
					var name = $(prop).data('context');
					self.assign_property(feature, name, {});
				}
			});

			if ($('input[name="wof_id"]').length > 0) {
				var id = $('input[name="wof_id"]').val();
				id = parseInt(id);
				feature.id = id;
				feature.properties['wof:id'] = id;
			}

			feature.properties['wof:parent_id'] = parseInt($('input[name="properties.wof:parent_id"]').val());
			feature.properties['wof:hierarchy'] = JSON.parse($('input[name="properties.wof:hierarchy"]').val());

			if (feature.properties['wof:placetype'] == 'venue') {
				feature.properties['mz:categories'] = self.get_categories();
				if ($('#hours').length > 0) {
					feature.properties['mz:hours'] = self.get_hours();
				}
			}
			self.generate_name_geojson(feature);

			return feature;
		},

		generate_geojson: function() {
			return JSON.stringify(self.generate_feature());
		},

		generate_name_geojson: function(feature){
			var names = {};
			var selector = '.names-language input.property, .names-language input.add-item';
			$(selector).each(function(i, input){
				var $parent = $(input).closest('.json-schema-array');
				var context = $parent.data('context');
				var match = context.match(/^names\.([^.]+)\.(.+)$/);
				if (match){
					var lang = match[1];
					var type = match[2];
					var prop = "name:" + lang + '_x_' + type;
					var value = $(input).val();
					if (value) {
						if (! names[prop]) {
							names[prop] = [];
						}
						names[prop].push(value);
					}
				}
			});
			$.each(names, function(prop, value){
				feature.properties[prop] = value;
			});
		},

		assign_property: function(context, name, value) {

			if (typeof name == 'string') {

				// Check if there are '.' chars in the name (object)
				var dot_match = name.match(/^([^.]+)\.(.+)/);

				// ... or if there is a square bracket pair (array)
				var bracket_match = name.match(/^([^.]+)\[(\d+)\]/);
			}

			if (dot_match) {

				// Looks like an object; recurse into the object context

				var context_key = dot_match[1];
				var key = dot_match[2];

				if (! context[context_key]) {
					context[context_key] = {};
				}

				self.assign_property(context[context_key], key, value);

			} else if (bracket_match) {

				// Looks like an array; recurse into the array context

				var key = bracket_match[1];
				var index = parseInt(bracket_match[2]);

				if (! context[key]) {
					context[key] = [];
				}

				self.assign_property(context[key], index, value);

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
				$('#edit-form .property-changed').removeClass('property-changed');
				self.initial_wof_value = self.generate_feature();
				if (! rsp['feature']) {
					$status.html('Error saving GeoJSON: Bad response from server.');
				} else if ($('input[name="wof_id"]').length == 0) {
					var wof_id = parseInt(rsp.feature.properties['wof:id']);

				    var url = '/id/' + wof_id + '/';
				    url = mapzen.whosonfirst.boundaryissues.utils.abs_root_urlify(url);
					location.href = url;
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

			lat = parseFloat(lat);
			lng = parseFloat(lng);

			var feature = self.generate_feature();
			feature.geometry = {
				type: "Point",
				coordinates: [lng, lat]
			}
			var geojson = JSON.stringify(feature);
			var data = {
				geojson: geojson
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
		},

		check_for_category_property: function() {

			var cats = self.get_categories();
			var icon = null;
			$.each(cats, function(i, cat) {
				if (self.categories.icon[cat]) {
					icon = self.categories.icon[cat];
				}
			});
			if (icon) {
				self.set_marker_icon(icon);
			}
			return icon;
		},

		user_signed_in: function() {
			return $(document.body).hasClass('user-signed-in');
		},

		user_can_edit: function() {
			return $(document.body).hasClass('user-can-edit');
		},

		set_controlled: function(property) {
			var feature = self.generate_feature();
			var controlled = feature.properties['wof:controlled'];
			if (controlled) {
				controlled.push(property);
			} else {
				controlled = [property];
			}
			self.set_property('wof:controlled', controlled);
		},

		is_controlled: function(property) {
			var feature = self.generate_feature();
			var controlled = feature.properties['wof:controlled'];
			if (! controlled) {
				return false;
			}
			return (controlled.indexOf(property) != -1);
		},

		disable_controlled: function(property) {
			var feature = self.generate_feature();
			var controlled = feature.properties['wof:controlled'];
			if (controlled) {
				var index = controlled.indexOf(property);
				if (index != -1) {
					controlled.splice(index, 1);
					self.set_property('wof:controlled', controlled);
				}
			}
		}
	};

	$(document).ready(function() {

		if ($('#edit-form').length == 0) {
			return;
		}

		// Check if we arrived by a URL like this: /add/?ll=123,456
		// which redirects to: /add/#16/123/456
		var ll = location.search.match(/ll=([^&]+)/);
		if (location.pathname.match(/add\/?$/) && ll) {
			window.location = location.pathname + '#16/' + ll[1].replace(',', '/');
			return;
		}

		var data_endpoint = $(document.body).attr("data-data-abs-root-url");
		mapzen.whosonfirst.uri.endpoint(data_endpoint);

		// We need to wait until the page has loaded before we can make
		// calls to mapzen.whosonfirst.boundaryissues.utils.abs_root_urlify.
		// (20160606/dphiffer)

		// Also this is the same code that's in mapzen.whosonfirst.boundaryissues.results
		// so it's probably time to merge them (20160603/thisisaaronland)

		// .... sooooon (20160606/dphiffer)

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

		self.setup_properties();
		self.setup_map();
		self.setup_form();
		self.setup_buttons();

		window.onbeforeunload = function(e) {
			if ($('#edit-form .property-changed').length > 0) {
				var dialogText = 'Discard unsaved changes?';
				e.returnValue = dialogText;
				return dialogText;
			} else {
				return;
			}
		};
	});

	return self;
})();
