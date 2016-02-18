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
				'venue-map',
				swlat, swlon, nelat, nelon
			);

			var drawnItems = new L.FeatureGroup();
			var markerLayer;
			map.addLayer(drawnItems);

			var VenueMarker = L.Icon.extend({
				options: {
					iconUrl: '/images/marker-icon.png',
					iconRetinaUrl: '/images/marker-icon-2x.png',
					shadowUrl: null,
					iconAnchor: new L.Point(13, 42),
					iconSize: new L.Point(25, 42),
					popupAnchor: new L.Point(0, -42)
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

			map.on('draw:drawstart', function(e){
				if (markerLayer){
					drawnItems.removeLayer(markerLayer);
					markerLayer = null;
				}
			});

			map.on('draw:created', function(e){
				markerLayer = e.layer;
				drawnItems.addLayer(markerLayer);

				var ll = markerLayer.getLatLng();
				$('#venue-coordinates').html('Venue coordinates: <strong>' + ll.lat + ', ' + ll.lng + '</strong>');
				$('input[name="geom:latitude"]').val(ll.lat);
				$('input[name="geom:longitude"]').val(ll.lng);

				// Clicking on the marker lets you reset the location
				markerLayer.on('click', function(e){
					drawnItems.removeLayer(markerLayer);
					markerLayer = null;
				});
			});

		}
	};

	$(document).ready(function(){
		self.setup_venue();
	});

	return self;
})();
