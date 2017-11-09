var mapzen = mapzen || {};
mapzen.whosonfirst = mapzen.whosonfirst || {};
mapzen.whosonfirst.boundaryissues = mapzen.whosonfirst.boundaryissues || {};

// for the time being this assumes jQuery is present
// that decision may change some day but not today
// (20160121/thisisaaronland)

mapzen.whosonfirst.boundaryissues.centroids = (function() {

	var centroids = {
		'geom': {
			name: 'math centroid',
			readonly: true
		},
		'lbl': {
			name: 'label centroid'
		},
		'reversegeo': {
			name: 'PIP centroid'
		}
	};

	var self = {

		update_where: function(centroid) {
			var lat = centroid.lat;
			var lng = centroid.lng;
			var name = centroid.prefix + ' centroid';
			if (centroids[centroid.prefix]) {
				name = centroids[centroid.prefix].name;
			}
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

		}

	};

	return self;
})();
