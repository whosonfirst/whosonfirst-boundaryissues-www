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
			           '<strong id="centroids-coords"></strong>' +
			           '<div id="centroids-about"></div>' +
			           '</div>';

			$('#where').html(html);
			$('#centroids-select').change(self.update_prefix);
			self.update_prefix();
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
				$('#centroids-coords').html('<a href="#">add new centroid</a>');
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
		}

	};

	return self;
})();
