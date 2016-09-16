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
	    poi_icon_base;

	var esc_str = mapzen.whosonfirst.php.htmlspecialchars;
	var esc_int = parseInt;
	var esc_float = parseFloat;

	// Return null (okay) or a string (error)
	var validations = [
		function() {
			var lat = $('input[name="properties.geom:latitude"]').val();
			var lng = $('input[name="properties.geom:longitude"]').val();
			if (! lat || ! lng) {
				return 'Please set <span class=\"hey-look\">geom:latitude</span> and <span class=\"hey-look\">geom:longitude</span>.';
			}
			return null;
		},
		function() {
			var wof_name = $('input[name="properties.wof:name"]').val();
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

			var placetype = $('input[name="properties.wof:placetype"]').val();
			if (placetype == 'venue') {
				self.setup_map_marker();
			} else {
				self.setup_map_geometry();
			}
			var geocoder = L.control.geocoder('search-o3YYmTI', {
				markers: {
					icon: new VenueIcon()
				}
			}).addTo(map);
			var hash = new L.Hash(map);

			self.show_nearby_results();
			map.on('dragend', function() {
				self.show_nearby_results();
			});

			slippymap.crosshairs.init(map);
			mapzen.whosonfirst.nearby.init(map);
			mapzen.whosonfirst.nearby.inflate_nearby();

			self.map = map;

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
		},

		setup_map_marker: function() {
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
		},

		setup_map_geometry: function() {
			// TODO: pick different lat/lng, perhaps using https://github.com/whosonfirst/whosonfirst-www-iplookup
			var lat = 40.73581157695217;
			var lon = -73.9815902709961;
			var zoom = 12;
			map = mapzen.whosonfirst.leaflet.tangram.map_with_latlon(
				'map',
				lat, lon, zoom
			);
			var geojson_url = $('#geojson-link').attr('href');
			$.get(geojson_url, function(feature) {
				mapzen.whosonfirst.leaflet.fit_map(map, feature);
				mapzen.whosonfirst.leaflet.draw_poly(map, feature, mapzen.whosonfirst.leaflet.styles.consensus_polygon());
			});
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

			self.group_properties();
			self.setup_add_property();

			$('.json-schema-object > table > tbody > tr').each(function(i, row) {
				if ($(row).hasClass('add-row')) {
					// Don't need to remove the placeholder input rows
					return;
				}
				if (! $(row).hasClass('property-deletable') ||
				    ! $(row).hasClass('property-editable')) {
					return;
				}
				$(row).find('> td > .json-schema-field').append('<button class="btn btn-remove-item">-</button>');
				$(row).find('.btn-remove-item').click(function(e) {
					$(row).remove();
				});
			});

			$('.json-schema-array > ul > li').each(function(i, row) {
				if (  $(row).hasClass('add-row') ||
				    ! $(row).closest('.object-property').hasClass('property-editable')) {
					return;
				}
				$(row).find('> .json-schema-field').append('<button class="btn btn-remove-item">-</button>');
				$(row).find('.btn-remove-item').click(function(e) {
					$(row).remove();
				});
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

			self.setup_categories();
			self.setup_hours();
			self.setup_address();
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

				var errors = [];
				$.each(validations, function(i, validate) {
					var result = validate();
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
				$('input[name="' + property + '"]').each(function(i, input){
					if ($(input).val() != value){
						$(input).val(value);
					}
				});
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

		setup_categories: function() {
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
			var days = ['Sunday', 'Monday', 'Tueday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
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
						console.log($prev);
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
			$('#address-btn').attr('disabled', null);
			$('#address-btn').click(function(e) {

				e.preventDefault();

				var data = {
					query: $('#address-query').val(),
					latitude: $('input[name="properties.wof:latitude"]').val(),
					longitude: $('input[name="properties.wof:longitude"]').val()
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
						html += '<dt>' + key + '</dt>' +
						        '<dd>' + value + '</dd>';
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

						$.each(rsp.properties, function(key, value) {
							self.set_property(key, value);
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

		get_property_rel: function(prefix){
			$rel = $('#property-group-' + prefix);
			if ($rel.length == 0){
				var collapsed = $('#mvp-heading').hasClass('collapsed') ? '' : ' collapsed';
				var html = '<h3 id="property-group-heading-' + prefix + '" class="property-group-heading' + collapsed + '">' + prefix + '</h3>' +
					   '<div id="property-group-' + prefix + '" class="property-group json-schema-object' + collapsed + '" data-context="properties"><table><tbody></tbody></table></div>';
				$('.property-group').last().after(html);
				$rel = $('#property-group-' + prefix);
				$('#property-group-heading-' + prefix).click(self.toggle_property_group);
			}
			return $rel;
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
				$.each(groups[prefix], function(j, $row){
					$('#property-group-' + prefix + ' > table > tbody').append($row);
					if ($row.hasClass('property-minimum_viable')){
						$('#json-schema-object-properties > table > tbody').append($row.clone());
					}
				});
			});

			$mvp = $('#json-schema-object-properties');
			$mvp.attr('id', 'property-group-minimum_viable');
			$mvp.addClass('property-group');
			$('#edit-properties > h3').remove();
			$mvp.before('<h4 id="mvp-heading" class="property-group-heading">Minimum viable properties</h4>');

			$('.property-group-heading').click(self.toggle_property_group);
		},

		toggle_property_group: function(e){
			var $group = $(e.target).next('.property-group');
			$group.toggleClass('collapsed');
			$target = $(e.target);
			$target.toggleClass('collapsed');

			$collapsed = $('.property-group-heading.collapsed');
			$headings = $('.property-group-heading');

			if ($target.attr('id') != 'mvp-heading'){
				if (! $target.hasClass('collapsed')){
					$('#mvp-heading, #property-group-minimum_viable').addClass('collapsed');
				} else if ($collapsed.length == $headings.length) {
					$('#mvp-heading, #property-group-minimum_viable').removeClass('collapsed');
				}
			}
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
			if ($('input[name="properties.' + property + '"]').length == 0) {
				var prefix = property.match(/^([a-z0-9_]+):/);
				if (prefix){
					var $rel = self.get_property_rel(prefix[1]);
				} else {
					mapzen.whosonfirst.log.error('Property ' + property + ' did not match the namespace:predicate regex.');
				}
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
			var $addRow = $rel.find('> table > tbody > .add-row');
			var context = $rel.data('context');
			if ($('input[name="' + context + '.' + key + '"]').length > 0) {
				alert('Oops, there is already a property with that name.');
				return;
			}
			var remove = '<button class="btn btn-remove-item">-</button>';
			var $newRow = $(
				'<tr>' +
					'<th>' + key + '</th>' +
					'<td><input type="text" name="' + context + '.' + key + '" class="property">' + remove + '</td>' +
				'</tr>'
			);
			if ($addRow.length) {
				$newRow.insertBefore($addRow);
			} else {
				$rel.find('> table > tbody').append($newRow);
			}
			$newRow.find('.btn-remove-item').click(function(e) {
				$newRow.remove();
			});

			$rel.find('input[name="' + context + '.' + key + '"]').val(value);
		},

		add_array_item: function($rel, value) {
			var context = $rel.data('context');
			var remove = '<button class="btn btn-remove-item">-</button>';
			var index = $rel.find('> ul > li').length;
			$rel.find('> ul').append(
				'<li>' +
					'<input name="' + context + '[' + index + ']" type="text" class="property">' + remove +
				'</li>'
			);
			var $new_item = $rel.find('> ul > li').last();
			$new_item.find('.btn-remove-item').click(function(e) {
				e.preventDefault();
				$new_item.remove();
			});
			$new_item.find('.property').val(value);
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

			    var url = '/id/' + id + '/';
			    url = mapzen.whosonfirst.boundaryissues.utils.abs_root_urlify(url);

				$('#parent').html('Parent: <a href="' + url + '">' + name + ' <code><small>' + id + '</small></code></a>');
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

			if (hierarchy.country_id) {
				self.set_country_properties(hierarchy.country_id);
			}
		},

		show_hierarchy: function(hierarchy) {
			var html = '<ul class="wof-hierarchy">';
			var labelRegex = /^(.+)_id$/;
			for (var key in hierarchy) {
				var id = esc_int(hierarchy[key]);
				var label = key;
				if (key.match(labelRegex)) {
					label = key.match(labelRegex)[1];
				}

				var url = '/belongsto/' + id + '/';
				url = mapzen.whosonfirst.boundaryissues.utils.abs_root_urlify(url);

				html += '<li>' + label + ': <a href="' + url + '" class="wof-namify hierarchy-' + id + '" data-wof-id="' + id + '">' + id + '</a></li>';
			}
			html += '</ul>';
			$('#hierarchy').append(html);

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
			var relpath = mapzen.whosonfirst.data.id2relpath(country_id);
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
					self.set_property('wof:country', props['iso:country']);
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

		generate_geojson: function() {

			var placetype = $('input[name="properties.wof:placetype"]').val();

			if (placetype == 'venue') {
				var lat = $('input[name="properties.geom:latitude"]').val();
				var lng = $('input[name="properties.geom:longitude"]').val();

				if (! lat || ! lng) {
					return null;
				}
				lat = parseFloat(lat);
				lng = parseFloat(lng);
				var geometry = {
					type: 'Point',
					coordinates: [lng, lat]
				};
			} else {
				var geometry_json = $('input[name="geometry"]').val();
				var geometry = JSON.parse(geometry_json);
			}

			var geojson_obj = {
				type: 'Feature',
				bbox: [lng, lat, lng, lat],
				geometry: geometry,
				properties: {}
			};

			$('#edit-form').find('.property').each(function(i, input) {
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

			// Some array properties are required and may not have any inputs to
			// iterate over. Encodes as [], when empty.
			$('.json-schema-required > .json-schema-array').each(function(i, prop) {
				if ($(prop).find('> ul > li').length == 0) {
					var name = $(prop).data('context');
					self.assign_property(geojson_obj, name, []);
				}
			});

			// I think this might only be for empty concordances dictionary,
			// at least for now. It'll encode as {} when empty.
			$('.json-schema-required > .json-schema-object').each(function(i, prop) {
				if ($(prop).find('> table > tbody > tr.object-property').length == 0) {
					var name = $(prop).data('context');
					self.assign_property(geojson_obj, name, {});
				}
			});

			if ($('input[name="wof_id"]').length > 0) {
				var id = $('input[name="wof_id"]').val();
				id = parseInt(id);
				geojson_obj.id = id;
				geojson_obj.properties['wof:id'] = id;
			}

			geojson_obj.properties['wof:parent_id'] = parseInt($('input[name="properties.wof:parent_id"]').val());
			geojson_obj.properties['wof:hierarchy'] = JSON.parse($('input[name="properties.wof:hierarchy"]').val());
			geojson_obj.properties['mz:categories'] = self.get_categories();

			if ($('#hours').length > 0){
				geojson_obj.properties['mz:hours'] = self.get_hours();
			}

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
		}

	};

	$(document).ready(function() {

		if ($('#edit-form').length == 0) {
			return;
		}

		var data_endpoint = $(document.body).attr("data-data-abs-root-url");
		mapzen.whosonfirst.data.endpoint(data_endpoint);

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

		self.setup_map();
		self.setup_drawing();
		self.setup_properties();
		self.setup_form();
		self.setup_buttons();
	});

	return self;
})();
