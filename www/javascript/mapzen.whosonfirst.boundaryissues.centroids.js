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
			description: 'Where the label should be displayed.'
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
			var options = '';
			for (var prefix in spec) {
				options += '<option>' + prefix + '</option>';
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
				var centroids = self.get_properties();
				self.update_where(centroids);
				mapzen.whosonfirst.boundaryissues.edit.show_centroids();
			});

			self.update_prefix();
			mapzen.whosonfirst.boundaryissues.edit.hide_centroids();
		},

		update_prefix: function() {
			var prefix = $('#centroids-select').val();
			var lat = $('input[name="properties.' + prefix + ':latitude"]').val();
			var lng = $('input[name="properties.' + prefix + ':longitude"]').val();
			if (lat && lng) {
				lat = parseFloat(lat).toFixed(6);
				lng = parseFloat(lng).toFixed(6);
				$('#centroids-coords').html(lat + ', ' + lng);
			} else {
				$('#centroids-coords').html('<a href="#" id="centroids-create">create</a>');
			}

			var name = self.prefix_name(prefix);
			var about = '<strong>' + name + '</strong>';
			if (spec[prefix].description) {
				about += '<br>' + htmlspecialchars(spec[prefix].description);
			}
			$('#centroids-about').html(about);
		},

		prefix_name: function(prefix) {
			var name = prefix + ' centroid';
			if (spec[prefix].name) {
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
				var src_centroid = prop.match(/src:([^:]+):centroid/);
				if (src_centroid &&
				    centroids.prefixes.indexOf(src_centroid[1]) == -1) {
					add_prefix_centroid(prefix);
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
		}

	};

	return self;
})();
