var mapzen = mapzen || {};
mapzen.whosonfirst = mapzen.whosonfirst || {};
mapzen.whosonfirst.boundaryissues = mapzen.whosonfirst.boundaryissues || {};

// for the time being this assumes jQuery is present
// that decision may change some day but not today
// (20160121/thisisaaronland)

mapzen.whosonfirst.boundaryissues.venue = (function() {

	var VenueIcon;

	var self = {
		map: null,
		country_id: -1,
		properties: {},

		set_country: function(country_id, cb) {

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

				if (props['iso:country']) {
					self.set_property('iso:country', props['iso:country']);
				}
				if (props['wof:country']) {
					self.set_property('wof:country', props['wof:country']);
				}

				if (cb) {
					cb(rsp);
				}
			};

			var on_failure = function(rsp) {
				mapzen.whosonfirst.log.error('Failed to set country properties.');
			}

			mapzen.whosonfirst.net.fetch(url, on_success, on_failure);
		},

		set_property: function(name, value) {
			self.properties[name] = value;
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
				properties: self.properties
			};

			feature.properties['wof:placetype'] = 'venue';
			feature.properties['wof:name'] = $('input[name="name"]').val();
			feature.properties['wof:tags'] = $('input[name="tags"]').val();
			feature.properties['wof:country'] = self.properties['wof:country'];
			feature.properties['iso:country'] = self.properties['iso:country'];
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

		var show_the_map = function(bbox) {
			var map = mapzen.whosonfirst.leaflet.tangram.map_with_bbox(
				'map',
				bbox[1],
				bbox[0],
				bbox[3],
				bbox[2]
			);

			var geocoder = L.control.geocoder('search-o3YYmTI', {
				markers: {
					icon: new VenueIcon()
				}
			}).addTo(map);

			geocoder.on('select', function(e) {
				var html = '<a href="#" class="btn btn-primary" id="geocoder-marker-select">Use this result</a> <a href="#" class="btn" id="geocoder-marker-cancel">Cancel</a>';
				var popup = geocoder.marker.bindPopup(html).openPopup();
				var props = e.feature.properties;
				if (e.feature.bbox) {
					var bbox = e.feature.bbox;
					var sw = L.latLng(bbox[1], bbox[0]);
					var ne = L.latLng(bbox[3], bbox[2]);
					var bounds = L.latLngBounds(sw, ne);
					map.fitBounds(bounds);
				}
				$('#geocoder-marker-select').click(function(e) {
					e.preventDefault();
					popup.closePopup();
					geocoder.collapse();
					var ll = geocoder.marker.getLatLng();
					map.removeLayer(geocoder.marker);
					map.setView(ll, 16);
					//self.lookup_hierarchy(ll.lat, ll.lng);
					//self.update_coordinates(ll, true);
					//self.set_marker(geocoder.marker);

					// This is a US-centric way of encoding an address
					var regex = new RegExp('^' + props.name);
					var address = props.label.replace(regex, props.housenumber + ' ' + props.street);

					if (! $('input[name="name"]').val()) {
						$('input[name="name"]').val(props.name);
					}
					if (! $('textarea[name="address"]').val()) {
						$('textarea[name="address"]').val(address);
					}

					self.set_property('addr:full', address);

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
			return map;
		};

		var onerror = function(rsp) {
			mapzen.whosonfirst.log.error("could not load ip service");
		};

		var onsuccess = function(rsp) {
			self.set_country(rsp.country_id, function(country) {
				var map = show_the_map(country.bbox);
				self.map = map;
				slippymap.crosshairs.init(map);
			});
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
