var mapzen = mapzen || {};
mapzen.whosonfirst = mapzen.whosonfirst || {};
mapzen.whosonfirst.boundaryissues = mapzen.whosonfirst.boundaryissues || {};

// for the time being this assumes jQuery is present
// that decision may change some day but not today
// (20160121/thisisaaronland)

mapzen.whosonfirst.boundaryissues.search = (function() {

	var map;

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
			L.control.geocoder('search-o3YYmTI', {
				markers: {
					icon: new VenueIcon()
				}
			}).addTo(map);
			var hash = new L.Hash(map);

			var markers = [];
			$('#search-results > li').each(function(i, result) {
				marker_style = self.get_venue_marker_style(result);
				var id = $(result).data('id');
				var marker = L.circleMarker({
					lat: parseFloat($(result).data('lat')),
					lng: parseFloat($(result).data('lng'))
				}, marker_style);
				marker._style = marker_style;

				markers.push(marker);
				marker.on('mouseover', function() {
					this.setStyle(mapzen.whosonfirst.leaflet.styles.venue_hover());
				});
				marker.on('mouseout', function() {
					this.setStyle(this._style);
				});
				marker.on('click', function() {
					location.href = '/id/' + parseInt(id) + '/';
				});
				marker.bindLabel($(result).find('a').html());
			});
			var group = L.featureGroup(markers).addTo(map);
			map.fitBounds(group.getBounds());
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

			map.on('draw:created', function(e){
				var ll = e.layer.getLatLng();
				var lat = parseFloat(ll.lat).toFixed(6);
				var lng = parseFloat(ll.lng).toFixed(6);
				var zoom = 16;
				var url = '/add/#' + zoom + '/' + lat + '/' + lng;
				location.href = url;
			});
		},

		get_venue_marker_style: function(item) {
			if ($(item).hasClass('iscurrent-yes')) {
				return mapzen.whosonfirst.leaflet.styles.venue_current();
			} else if ($(item).hasClass('deprecated')) {
				return mapzen.whosonfirst.leaflet.styles.venue_deprecated();
			} else if ($(item).hasClass('iscurrent-no')) {
				return mapzen.whosonfirst.leaflet.styles.venue_not_current();
			} else {
				return mapzen.whosonfirst.leaflet.styles.venue_unknown();
			}
		}

	};

	$(document).ready(function() {
		if ($('#search-results').length == 0) {
			return;
		}
		self.setup_map();
		self.setup_drawing();
	});

	return self;
})();
