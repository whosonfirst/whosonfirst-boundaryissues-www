var mapzen = mapzen || {};
mapzen.whosonfirst = mapzen.whosonfirst || {};
mapzen.whosonfirst.boundaryissues = mapzen.whosonfirst.boundaryissues || {};

// for the time being this assumes jQuery is present
// that decision may change some day but not today
// (20160121/thisisaaronland)

mapzen.whosonfirst.boundaryissues.venue = (function() {

	var self = {
		map: null,
		country_id: -1,
		country: '',

		set_country: function(country_id) {

			if (country_id == self.country_id) {
				// No change in the ID, so no need to reload the WOF record
				return;
			}

			var base_url = $('body').data('data-abs-root-url');
			var relpath = mapzen.whosonfirst.data.id2relpath(country_id);
			var url = base_url + relpath;

			var on_success = function(rsp) {
				if (! rsp.properties) {
					mapzen.whosonfirst.log.error('Tried to set country properties, but the country WOF record had no properties.');
					return;
				}
				var props = rsp.properties;

				// This treats iso:country and wof:country as equivalent
				// Is that a good assumption? (20170221/dphiffer)
				if (props['iso:country']) {
					self.country = props['iso:country'];
				} else if (props['wof:country']) {
					self.country = props['wof:country'];
				}
			};

			var on_failure = function(rsp) {
				mapzen.whosonfirst.log.error('Failed to set country properties.');
			}

			mapzen.whosonfirst.net.fetch(url, on_success, on_failure);
		},

		generate_feature: function() {
			var center = self.map.getCenter();
			var lat = center.lat;
			var lng = center.lng;
			var geometry = {
				type: 'Point',
				coordinates: [lng, lat]
			};
			var bbox = [lng, lat, lng, lat];

			var feature = {
				type: 'Feature',
				bbox: bbox,
				geometry: geometry,
				properties: {}
			};

			feature.properties['wof:placetype'] = 'venue';
			feature.properties['wof:name'] = $('input[name="name"]').val();
			feature.properties['wof:country'] = self.country;
			feature.properties['iso:country'] = self.country;
			feature.properties['geom:latitude'] = lat;
			feature.properties['geom:longitude'] = lng;
			feature.properties['wof:parent_id'] = -1;
			feature.properties['wof:hierarchy'] = [];

			return feature;
		},

		generate_geojson: function() {
			return JSON.stringify(self.generate_feature());
		},

		save_to_server: function(geojson) {

			var data = {
				crumb: $('#venue').data('crumb-save'),
				geojson: geojson
			};

			var onsuccess = function(rsp) {
				if (! rsp.feature ||
				    ! rsp.feature.properties ||
				    ! rsp.feature.properties['wof:id']) {
					mapzen.whosonfirst.log.error("no feature returned from wof.save");
					return;
				}
				var wof_id = parseInt(rsp.feature.properties['wof:id']);
				var url = '/id/' + wof_id + '/';
				url = mapzen.whosonfirst.boundaryissues.utils.abs_root_urlify(url);
				location.href = url;
			};

			var onerror = function(rsp) {
				mapzen.whosonfirst.log.error("error calling wof.save");
			};

			mapzen.whosonfirst.boundaryissues.api.api_call("wof.save", data, onsuccess, onerror);
		},
	};

	function setup_map() {

		var scene = mapzen.whosonfirst.boundaryissues.utils.abs_root_urlify('/tangram/refill.yaml');
		mapzen.whosonfirst.leaflet.tangram.scenefile(scene);

		var show_the_map = function(bbox) {
			if (bbox) {
				// Parse and map from a bounding box string
				bbox_coords = bbox.split(',');
				var map = mapzen.whosonfirst.leaflet.tangram.map_with_bbox(
					'map',
					parseFloat(bbox_coords[1]),
					parseFloat(bbox_coords[0]),
					parseFloat(bbox_coords[3]),
					parseFloat(bbox_coords[2])
				);
			} else {
				// Ok, fine just show NYC
				var lat = 40.73581157695217;
				var lon = -73.9815902709961;
				var zoom = 12;
				var map = mapzen.whosonfirst.leaflet.tangram.map_with_latlon(
					'map',
					lat, lon, zoom
				);
			}
			return map;
		};

		var onerror = function(rsp) {
			mapzen.whosonfirst.log.error("could not load ip service");
		};

		var onsuccess = function(rsp) {
			if (rsp.geom_bbox) {
				var map = show_the_map(rsp.geom_bbox);
			} else {
				var map = show_the_map();
			}
			self.map = map;
			slippymap.crosshairs.init(map);
			self.set_country(rsp.country_id);
		};

		var url = 'https://ip.dev.mapzen.com/?raw=1';
		mapzen.whosonfirst.net.fetch(url, onsuccess, onerror);
	}

	function setup_form() {
		$('#venue form').submit(function(e) {
			e.preventDefault();
			var geojson = self.generate_geojson();
			self.save_to_server(geojson);
		});
	}

	$(document).ready(function() {
		if ($('#venue').length > 0) {
			setup_map();
			setup_form();
		}
	});

	return self;

})();
