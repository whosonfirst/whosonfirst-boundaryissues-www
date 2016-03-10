var mapzen = mapzen || {};
mapzen.whosonfirst = mapzen.whosonfirst || {};
mapzen.whosonfirst.boundaryissues = mapzen.whosonfirst.boundaryissues || {};

// for the time being this assumes jQuery is present
// that decision may change some day but not today
// (20160121/thisisaaronland)

mapzen.whosonfirst.boundaryissues.search = (function() {

	var map;

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

	var self = {

		setup_map: function() {
			var lat = 40.73581157695217;
			var lon = -73.9815902709961;
			var zoom = 12;
			mapzen.whosonfirst.leaflet.tangram.scenefile('/tangram/refill.yaml');
			map = mapzen.whosonfirst.leaflet.tangram.map_with_latlon(
				'map',
				lat, lon, zoom
			);
			var markers = [];
			$('#search-results > li').each(function(i, result) {
				var id = $(result).data('id');
				var marker = L.circleMarker({
					lat: parseFloat($(result).data('lat')),
					lng: parseFloat($(result).data('lng'))
				}, marker_style);
				markers.push(marker);
				marker.on('mouseover', function() {
					this.setStyle(marker_hover_style);
				});
				marker.on('mouseout', function() {
					this.setStyle(marker_style);
				});
				marker.on('click', function() {
					location.href = '/id/' + parseInt(id) + '/';
				});
				marker.bindLabel($(result).find('a').html());
			});
			var group = L.featureGroup(markers).addTo(map);
			map.fitBounds(group.getBounds());
		}

	};

	$(document).ready(function() {
		if ($('#search-results').length == 0) {
			return;
		}
		self.setup_map();
	});

	return self;
})();
