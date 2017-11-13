var mapzen = mapzen || {};
mapzen.whosonfirst = mapzen.whosonfirst || {};
mapzen.whosonfirst.boundaryissues = mapzen.whosonfirst.boundaryissues || {};

// for the time being this assumes jQuery is present
// that decision may change some day but not today
// (20160121/thisisaaronland)

mapzen.whosonfirst.boundaryissues.centroids = (function() {

	var spec = {
		'geom': {
			name: 'math centroid',
			type: 'common',
			description: 'The math centroid of the area polygon.',
			readonly: true
		},
		'intersection': {
			type: 'optional'
		},
		'lbl': {
			name: 'label centroid',
			type: 'common',
			description: 'Where the label should be displayed.',
			control_on_change: true
		},
		'local': {
			type: 'optional',
			description: 'Where do locals from this place consider the "center".'
		},
		'nav': {
			name: 'navigation centroid',
			type: 'common-optional',
			description: 'Snapped to nearest road and used to provide accurate turn-by-turn directions and navigation ETAs (expected time of arrivals).'
		},
		'reversegeo': {
			name: 'PIP centroid',
			type: 'common-optional',
			description: 'Used for point-in-polygon (PIP) processing.'
		},
		'tourist': {
			type: 'optional',
			description: 'Tourists follow guides and flock towards famous attractions.'
		}
	};

	// These have known meanings, don't allow them to be edited as centroids.
	var custom_prefix_blacklist = [
		'gn',
		'mps',
		'ne',
		'wk'
	];

	var markers = [];

	var self = {

		update_where: function(centroid) {
			var lat = centroid.lat;
			var lng = centroid.lng;
			var name = self.prefix_name(centroid.prefix);
			var html = name + ': <strong>' + lat + ', ' + lng + '</strong>' +
			           '<span id="where-parent"></span>';

			if ($('body').hasClass('user-can-edit')) {
				html += ' <a href="#edit-centroids" id="centroids-edit">edit</a>';
			}

			$('#where').html(html);
			$('#centroids-edit').click(function(e) {
				e.preventDefault();
				self.editor_init();
			});
		},

		editor_init: function() {
			var centroids = self.get_properties();
			var options = '<option value="">Custom...</option>';
			for (var prefix in spec) {
				options += '<option>' + prefix + '</option>';
			}

			for (var i = 0; i < centroids.prefixes.length; i++) {
				var prefix = centroids.prefixes[i];
				if (! spec[prefix]) {
					options += '<option>' + prefix + '</option>';
				}
			}

			var html = '<div id="centroids-editor">' +
			           '<select id="centroids-select">' + options + '</select> ' +
			           '<strong id="centroids-coords"></strong> <a href="#" id="centroids-done">done</a>' +
			           '<div id="centroids-about"></div>' +
			           '</div>';

			$('#where').html(html);
			$('#centroids-select').change(self.update_prefix);
			$('#centroids-done').click(function(e) {
				e.preventDefault();
				self.editor_done();
			});

			self.update_prefix(centroids.prefix);
			mapzen.whosonfirst.boundaryissues.edit.hide_centroids();

			for (var i = 0; i < centroids.prefixes.length; i++) {
				self.show_centroid(centroids, centroids.prefixes[i]);
			}
		},

		editor_done: function() {
			var centroids = self.get_properties();
			self.update_where(centroids);
			mapzen.whosonfirst.boundaryissues.edit.show_centroids();
			var map = mapzen.whosonfirst.boundaryissues.edit.get_map();
			for (var i = 0; i < markers.length; i++) {
				map.removeLayer(markers[i]);
			}
		},

		update_prefix: function(prefix) {
			if (typeof prefix != 'string') {
				prefix = $('#centroids-select').val();
				if (prefix == '') {
					prefix = prompt('Please enter the centroid prefix.');
					if (custom_prefix_blacklist.indexOf(prefix) != -1) {
						alert('Sorry, the prefix "' + prefix + '" has an existing known meaning and cannot be edited as a centroid.');
						prefix = null;
					}
					var known_prefixes = [];
					var options = $('#centroids-select')[0].options;
					for (var i = 0; i < options.length; i++) {
						if (options[i].value == prefix) {
							self.update_prefix(prefix);
							return false;
						}
					}
					if (! prefix) {
						// Cancel button
						var centroids = self.get_properties();
						$('#centroids-select').val(centroids.prefix);
						return false;
					}
					if (! prefix.match(/^[a-z]+$/)) {
						alert('Sorry, that prefix is invalid.');
						var centroids = self.get_properties();
						$('#centroids-select').val(centroids.prefix);
						return false;
					}
					var options = $('#centroids-select')[0].options;
					var index = options.length;
					options[index] = new Option(prefix);
					self.create_centroid(prefix);
					$('#centroids-select')[0].selectedIndex = index;
				}
			} else {
				if ($('#centroids-select').val() == prefix) {
					return;
				}
				$('#centroids-select').val(prefix);
			}
			var lat = $('input[name="properties.' + prefix + ':latitude"]').val();
			var lng = $('input[name="properties.' + prefix + ':longitude"]').val();
			if (lat && lng) {
				lat = parseFloat(lat).toFixed(6);
				lng = parseFloat(lng).toFixed(6);
				$('#centroids-coords').html(lat + ', ' + lng);
			} else {
				$('#centroids-coords').html('<a href="#" id="centroids-create">create</a>');
			}

			$('#centroids-create').click(function(e) {
				e.preventDefault();
				self.create_centroid(prefix);
			});

			var name = self.prefix_name(prefix);
			var about = '<strong>' + name + '</strong>';
			if (spec[prefix] && spec[prefix].description) {
				about += '<br>' + htmlspecialchars(spec[prefix].description);
			}
			$('#centroids-about').html(about);

			$('.centroid-selected').removeClass('centroid-selected');
			$('.' + prefix + '-centroid').addClass('centroid-selected');
		},

		create_centroid: function(prefix) {
			var map = mapzen.whosonfirst.boundaryissues.edit.get_map();
			var ll = map.getCenter();
			var lat = ll.lat.toFixed(6);
			var lng = ll.lng.toFixed(6);
			mapzen.whosonfirst.boundaryissues.edit.set_property(prefix + ':latitude', lat);
			mapzen.whosonfirst.boundaryissues.edit.set_property(prefix + ':longitude', lng);
			mapzen.whosonfirst.boundaryissues.edit.set_property('src:' + prefix + ':centroid', 'mz');
			var centroids = self.get_properties();
			centroids.prefix = prefix;
			centroids[prefix] = {
				lat: lat,
				lng: lng
			};
			self.show_centroid(centroids, prefix);
			$('#centroids-coords').html(lat + ', ' + lng);
		},

		show_centroid: function(centroids, prefix) {
			var icon_class = 'centroid ' + prefix + '-centroid';
			var draggable = true;
			var z_offset = 0;
			if (centroids.prefix == prefix) {
				icon_class += ' centroid-selected';
				z_offset = 1000;
			}
			if (spec[prefix] && spec[prefix].readonly) {
				icon_class += ' centroid-readonly';
				draggable = false;
			}
			var icon = L.divIcon({
				className: icon_class
			});
			var map = mapzen.whosonfirst.boundaryissues.edit.get_map();
			var ll = [centroids[prefix].lat, centroids[prefix].lng];
			var m = L.marker(ll, {
				icon: icon,
				draggable: draggable,
				zIndexOffset: z_offset
			}).addTo(map);
			m.on('dragstart', function() {
				$('.centroid-selected').removeClass('centroid-selected');
				$('.' + prefix + '-centroid').addClass('centroid-selected');
				mapzen.whosonfirst.boundaryissues.edit.set_property('src:' + prefix + ':centroid', 'mz');
				self.update_prefix(prefix);
			});
			m.on('click', function() {
				$('.centroid-selected').removeClass('centroid-selected');
				$('.' + prefix + '-centroid').addClass('centroid-selected');
				self.update_prefix(prefix);
			});
			m.on('dragend', function() {
				var ll = m.getLatLng();
				var lat = ll.lat.toFixed(6);
				var lng = ll.lng.toFixed(6);
				mapzen.whosonfirst.boundaryissues.edit.set_property(prefix + ':latitude', lat);
				mapzen.whosonfirst.boundaryissues.edit.set_property(prefix + ':longitude', lng);
				$('#centroids-coords').html(lat + ', ' + lng);
			});
			markers.push(m);
			m.bindTooltip(self.prefix_name(prefix) + ' (' + prefix + ')');
		},

		prefix_name: function(prefix) {
			var name = prefix + ' centroid';
			if (spec[prefix] && spec[prefix].name) {
				name = spec[prefix].name;
			}
			return name;
		},

		get_properties: function() {

			var centroids = {
				prefix: null,
				prefixes: []
			};

			function add_prefix_centroid(prefix) {
				var $lat = $('input[name="properties.' + prefix + ':latitude"]');
				var $lng = $('input[name="properties.' + prefix + ':longitude"]');
				if (! $lat.val() ||
				    ! $lng.val()) {
					return;
				}
				centroids.prefixes.push(prefix);
				centroids[prefix] = {
					lat: parseFloat($lat.val()),
					lng: parseFloat($lng.val())
				};
			}

			// Check for known centroids
			for (var prefix in spec) {
				add_prefix_centroid(prefix);
			}

			// Check for additional centroids, based on src:[prefix]:centroid
			// properties
			var feature = mapzen.whosonfirst.boundaryissues.edit.generate_feature();
			for (var prop in feature.properties) {
				var src_centroid = prop.match(/^src:([^:]+):centroid$/);
				if (src_centroid &&
				    centroids.prefixes.indexOf(src_centroid[1]) == -1) {
					add_prefix_centroid(src_centroid[1]);
				}
			}

			// Pick the best centroid for general use
			var precedence = ['lbl', 'reversegeo', 'geom'];
			for (var i = 0; i < precedence.length; i++) {
				var prefix = precedence[i];
				if (centroids[prefix]) {
					centroids.prefix = prefix;
					centroids.lat = centroids[prefix].lat;
					centroids.lng = centroids[prefix].lng;
					break;
				}
			}

			return centroids;
		},

		centroid_changed: function(prefix) {
			if (spec[prefix] && spec[prefix].control_on_change) {
				var feature = mapzen.whosonfirst.boundaryissues.edit.generate_feature();
				var props = feature.properties;
				var controlled = props['wof:controlled'] || [];
				if (controlled.indexOf(prefix + ':latitude') == -1) {
					controlled.push(prefix + ':latitude');
				}
				if (controlled.indexOf(prefix + ':longitude') == -1) {
					controlled.push(prefix + ':longitude');
				}
				mapzen.whosonfirst.boundaryissues.edit.set_property('wof:controlled', controlled);
			}
		}

	};

	$(document).ready(function() {
		$('#edit-form').on('propertychanged', function(e, property, value) {
			var match = property.match(/^properties.([^:]+):(latitude|longitude)$/);
			if (match) {
				var prefix = match[1];
				self.centroid_changed(prefix);
			}
		});
	});

	return self;
})();
