var mapzen = mapzen || {};
mapzen.whosonfirst = mapzen.whosonfirst || {};
mapzen.whosonfirst.boundaryissues = mapzen.whosonfirst.boundaryissues || {};

// for the time being this assumes jQuery is present
// that decision may change some day but not today
// (20160121/thisisaaronland)

mapzen.whosonfirst.boundaryissues.venue = (function(){

	var self = {
		setup_venue: function(){
			var swlat = 37.70120736474139;
			var swlon = -122.68707275390624;
			var nelat = 37.80924146650164;
			var nelon = -122.21912384033203;

			mapzen.whosonfirst.leaflet.tangram.scenefile('/tangram/refill.yaml');
			var map = mapzen.whosonfirst.leaflet.tangram.map_with_bbox(
				'map',
				swlat, swlon, nelat, nelon
			);

			var drawnItems = new L.FeatureGroup();
			map.addLayer(drawnItems);


			var VenueMarker = L.Icon.extend({
				options: {
					iconUrl: '/images/marker-icon.png',
					iconRetinaUrl: '/images/marker-icon-2x.png',
					shadowUrl: null,
					iconAnchor: new L.Point(13, 41),
					iconSize: new L.Point(25, 41)
				}
			});
			var drawControl = new L.Control.Draw({
				draw: {
					polyline: false,
					polygon: false,
					rectangle: false,
					circle: false,
					marker: {
						icon: new VenueMarker()
					}
				},
				edit: {
					featureGroup: drawnItems,
					edit: false,
					remove: false
				}
			});
			map.addControl(drawControl);

			map.on('draw:created', function (e) {
				var type = e.layerType,
				layer = e.layer;

				if (type === 'marker') {
					layer.bindPopup('A popup!');
				}

				drawnItems.addLayer(layer);
			});
		}
	};

	$(document).ready(function(){
		self.setup_venue();
	});

	return self;
})();
