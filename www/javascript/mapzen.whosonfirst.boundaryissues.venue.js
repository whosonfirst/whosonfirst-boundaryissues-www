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
		properties: {},
		country_id: -1,

		set_property: function(name, value) {
			self.properties[name] = value;
		},

		set_country: function(country) {

			if (country.properties['wof:id'] == self.country_id) {
				// No change in the ID, so no need to reload the WOF record
				return;
			}

			var props = country.properties;

			if (props['iso:country']) {
				self.set_property('iso:country', props['iso:country']);
			}
			if (props['wof:country']) {
				self.set_property('wof:country', props['wof:country']);
			}
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

			var tags = $('input[name="tags"]').val();
			tags = tags.split(',');
			for (var i = 0; i < tags.length; i++) {
				tags[i] = tags[i].trim();
			}

			feature.properties['wof:placetype'] = 'venue';
			feature.properties['wof:name'] = $('input[name="name"]').val();
			feature.properties['wof:tags'] = tags;
			feature.properties['addr:full'] = $('textarea[name="address"]').val();
			feature.properties['geom:latitude'] = lat;
			feature.properties['geom:longitude'] = lng;

			return feature;
		},

		generate_geojson: function() {
			return JSON.stringify(self.generate_feature());
		},

		save_to_server: function(geojson, succes_cb, error_cb) {

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
				if (succes_cb) {
					var wof_id = parseInt(rsp.feature.properties['wof:id']);
					succes_cb(wof_id);
				}
			};

			var onerror = function(rsp) {
				if (error_cb) {
					error_cb(rsp);
				}
				mapzen.whosonfirst.log.error("error calling wof.save");
			};

			mapzen.whosonfirst.boundaryissues.api.api_call("wof.save", data, onsuccess, onerror);
		},

		'lookup_hierarchy': function(ll) {

			var data = {
				latitude: ll.lat,
				longitude: ll.lng,
				placetype: 'venue'
			};

			var onsuccess = function(rsp) {
				if (! rsp.ok) {
					mapzen.whosonfirst.log.error('Error reverse geocoding.');
					return;
				}
				if (rsp.parents && rsp.parents.length == 1) {
					self.set_property('wof:parent_id', rsp.parents[0].Id);
				} else {
					self.set_property('wof:parent_id', -1);
				}
				if (rsp.hierarchy) {
					self.set_property('wof:hierarchy', rsp.hierarchy);
				} else {
					self.set_property('wof:hierarchy', []);
				}
			}

			var onerror = function(rsp) {
				mapzen.whosonfirst.log.error('Error reverse geocoding.');
			};

			mapzen.whosonfirst.boundaryissues.api.api_call("wof.pip", data, onsuccess, onerror);
		},

		'show_feature_pin': function(map, geocoder, feature) {
			var html = '<a href="#" class="btn btn-primary" id="geocoder-marker-select">Use this result</a> <a href="#" class="btn" id="geocoder-marker-cancel">Cancel</a>';
			var popup = geocoder.marker.bindPopup(html).openPopup();
			var props = feature.properties;
			if (feature.bbox) {
				mapzen.whosonfirst.boundaryissues.bbox.set_bbox(map, feature.bbox);
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
		},

		geocode_address: function(address) {
			var api_key = $('#venue-lookup-address').data('api-key');
			var esc_address = encodeURIComponent(address);
			var url = 'https://search.mapzen.com/v1/search?text=' + esc_address + '&api_key=' + api_key;
			var onsuccess = function(rsp) {
				if (rsp && rsp.features &&
				    rsp.features.length > 0) {
					// For now, just go with result #1
					var f = rsp.features[0];
					var c = f.geometry.coordinates;
					var lng = c[0];
					var lat = c[1];
					self.map.setView([lat, lng], 16);

					var ll = self.map.getCenter();
					self.lookup_hierarchy(ll);
				}
				$('#venue-lookup-address').removeClass('loading');
			};
			var onerror = function() {
				mapzen.whosonfirst.log.error("unable to geocode address: " + address);
			};
			$('#venue-lookup-address').addClass('loading');
			mapzen.whosonfirst.net.fetch(url, onsuccess, onerror);
		}
	};

	function setup_map() {

		var scene = mapzen.whosonfirst.boundaryissues.utils.abs_root_urlify('/tangram/refill.yaml');
		mapzen.whosonfirst.leaflet.tangram.scenefile(scene);

		var map = mapzen.whosonfirst.leaflet.tangram.map('map');
		self.map = map;
		var hash = new L.Hash(map);

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

		var geocoder = L.control.geocoder('search-o3YYmTI', {
			markers: {
				icon: new VenueIcon()
			}
		}).addTo(map);
		L.control.locate().addTo(map);

		mapzen.whosonfirst.boundaryissues.bbox.init(map, function(rsp) {
			self.set_country(rsp.country);
			slippymap.crosshairs.init(map);
		});

		map.on('moveend', function() {
			var ll = map.getCenter();
			self.lookup_hierarchy(ll);
		});

		geocoder.on('select', function(e) {
			self.show_feature_pin(map, geocoder, e.feature);
		});
	}

	function setup_form() {

		function onsuccess(id) {
			var csv_url = window.location.href.match(/csv\/([^\/]+)\/(\d+)\/?/);
			if (csv_url) {
				var csv_id = csv_url[1];
				var page = parseInt(csv_url[2]);
				if (page == csv_row_count) {
					$('#venue-response').html('<div class="alert alert-success">You have finished importing the CSV file.</div>');
					$('input[name="name"]').val('');
					$('textarea[name="address"]').val('');
					$('input[name="tags"]').val('');
					window.scrollTo(0, 0);
				} else {
					window.location = mapzen.whosonfirst.boundaryissues.utils.abs_root_urlify('/csv/' + csv_id + '/' + (page + 1) + '/');
				}
			} else {
				var edit_url = mapzen.whosonfirst.boundaryissues.utils.abs_root_urlify('/id/' + id + '/');
				$('#venue-response').html('<div class="alert alert-success">Your venue has been saved. You can <a href="' + edit_url + '">edit the WOF record</a> or add another venue.</div>');
				$('input[name="name"]').val('');
				$('textarea[name="address"]').val('');
				$('input[name="tags"]').val('');
				window.scrollTo(0, 0);
			}
		}

		function onerror(rsp) {
			var message = '😿 There was a problem saving your venue.';
			if (rsp.error && rsp.error.message) {
				message = '<strong>Oops!</strong> ' + htmlspecialchars(rsp.error.message);
			}
			$('#venue-response').html('<div class="alert alert-danger">' + message + '</div>');
		}

		$('#venue form').submit(function(e) {
			e.preventDefault();
			if ($('input[name="name"]').val() != '') {
				$('#venue-response').html('<div class="alert alert-info">Saving venue...</div>');
				var geojson = self.generate_geojson();
				self.save_to_server(geojson, onsuccess, onerror);
			} else {
				$('#venue-response').html('<div class="alert alert-warning">Oops, you forgot to enter a name for your venue.</div>');
			}
		});
	}

	function setup_address() {
		$('#venue-lookup-address').click(function(e) {
			e.preventDefault();
			var address = $('textarea[name="address"]').val();
			var ll = address.match(/(-?\d+\.?\d*)\s*,\s*(-?\d+\.?\d*)/);
			if (ll) {
				var lat = parseFloat(ll[1]);
				var lng = parseFloat(ll[2]);
				self.map.setView([lat, lng], 16);
				$('textarea[name="address"]').val('');
			} else {
				self.geocode_address(address);
			}
		});
	}

	function check_for_assignments() {
		if (! venue_assignments) {
			return;
		}
		$.each(venue_assignments, function(key, value) {
			self.set_property(key, value);
			if (key == 'wof:name') {
				$('input[name="name"]').val(value);
			} else if (key == 'addr:full') {
				$('textarea[name="address"]').val(value);
			} else if (key == 'wof:tags') {
				$('input[name="tags"]').val(value);
			}
		});
		if (venue_assignments['geom:latitude'] &&
		    venue_assignments['geom:longitude']) {
			var lat = parseFloat(venue_assignments['geom:latitude']);
			var lng = parseFloat(venue_assignments['geom:longitude']);
			self.map.setView([lat, lng], 16);
		} else {
			self.geocode_address(venue_assignments['addr:full']);
		}
	}

	$(document).ready(function() {
		if ($('#venue').length > 0) {

			// Check if we arrived by a URL like this: /add/?ll=123,456
			// which redirects to: /add/#16/123/456
			var ll = location.search.match(/ll=([^&]+)/);
			if (location.pathname.match(/add\/?$/) && ll) {
				window.location = location.pathname + '#16/' + ll[1].replace(',', '/');
				return;
			}

			setup_map();
			setup_form();
			setup_address();
			check_for_assignments();
		}
	});

	return self;

})();
