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
		properties: {
			'wof:placetype': 'venue'
		},
		country_id: -1,

		reset_properties: function() {
			self.properties = {
				'wof:placetype': 'venue'
			};
			$('input[name="name"]').val('');
			$('textarea[name="address"]').val('');
			$('input[name="tags"]').val('');
		},

		set_property: function(name, value) {
			self.properties[name] = value;
			if (typeof value == 'object') {
				value = JSON.stringify(value);
			}
			$('td[data-property="' + name + '"]').html(htmlspecialchars(value));
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

			var lat = parseFloat(self.properties['geom:latitude']);
			var lng = parseFloat(self.properties['geom:longitude']);

			var feature = {
				type: 'Feature',
				bbox: [lng, lat, lng, lat],
				geometry: {
					type: 'Point',
					coordinates: [lng, lat]
				},
				properties: self.properties
			};

			return feature;
		},

		generate_geojson: function() {
			return JSON.stringify(self.generate_feature());
		},

		save_to_server: function(geojson, success_cb, error_cb) {

			var data = {
				crumb: $('#venue').data('crumb-save'),
				geojson: geojson,
				csv_id: $('#csv_id').val(),
				csv_row: $('#csv_row').val()
			};

			var onsuccess = function(rsp) {
				if (! rsp.feature ||
				    ! rsp.feature.properties ||
				    ! rsp.feature.properties['wof:id']) {
					mapzen.whosonfirst.log.error("no feature returned from wof.save");
					return;
				}
				if (success_cb) {
					var wof_id = parseInt(rsp.feature.properties['wof:id']);
					success_cb(wof_id);
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

		lookup_hierarchy: function(ll) {

			self.looking_up_hierarchy = true;

			var data = {
				latitude: ll.lat,
				longitude: ll.lng,
				placetype: 'venue'
			};

			var onsuccess = function(rsp) {
				self.looking_up_hierarchy = false;
				if (! rsp.ok) {
					var message = 'Error reverse geocoding';
					if (rsp.error) {
						message += ': ' + rsp.error;
					}
					mapzen.whosonfirst.log.error(message);
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
				if (self.hierarchy_callback) {
					self.hierarchy_callback();
				}
			}

			var onerror = function(rsp) {
				self.looking_up_hierarchy = false;
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
				self.set_property('geom:latitude', ll.lat);
				self.set_property('geom:longitude', ll.lng);
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

		geocode_address: function(address, cb) {
			//var api_key = $('#venue-lookup-address').data('api-key');
			// Hardcoding this until we work out the spatial stuff on prod
			var api_key = 'mapzen-LhT76h5';

			var esc_address = encodeURIComponent(address);
			var url = 'https://search.mapzen.com/v1/search?text=' + esc_address + '&api_key=' + api_key;
			var onsuccess = function(rsp) {
				if (rsp && rsp.features && rsp.features.length == 1) {
					var c = rsp.features[0].geometry.coordinates;
					var lng = c[0];
					var lat = c[1];
					self.select_geocoded(lat, lng);
				} else if (rsp && rsp.features && rsp.features.length > 1) {
					var html = '<ul class="list-group">';
					$.each(rsp.features, function(i, f) {
						var c = f.geometry.coordinates;
						var lng = c[0];
						var lat = c[1];
						var label = f.properties.name;
						if (f.properties.locality) {
							label += ', ' + f.properties.locality;
						}
						if (f.properties.region_a) {
							label += ', ' + f.properties.region_a;
						}
						html += '<li class="list-group-item"><a href="#" data-lat="' + htmlspecialchars(lat) + '" data-lng="' + htmlspecialchars(lng) + '" class="geocoded">' + htmlspecialchars(label) + '</a></li>';
					});
					html += '</ul>';

					if ($('#venue-lookup-geocoded').length == 0) {
						$('#venue-lookup-address').after('<div id="venue-lookup-geocoded"></div>');
					}

					$('#venue-lookup-geocoded').html(html);
					$('#venue-lookup-geocoded a.geocoded').click(function(e) {
						e.preventDefault();
						var lat = parseFloat($(e.target).data('lat'));
						var lng = parseFloat($(e.target).data('lng'));
						self.select_geocoded(lat, lng);
						$('#venue-lookup-geocoded').html('');
						$('#venue-lookup-address').removeClass('choose-address');
					});

					var first_link = $('#venue-lookup-geocoded a.geocoded').get(0);
					var lat = parseFloat($(first_link).data('lat'));
					var lng = parseFloat($(first_link).data('lng'));
					self.select_geocoded(lat, lng);
					$('#venue-lookup-address').addClass('choose-address');
				} else {
					$('#venue-lookup-geocoded').html('<i>No results</i>');
				}

				$('#venue-lookup-address').removeClass('loading');
				if (cb) {
					cb(rsp);
				}
			};
			var onerror = function() {
				mapzen.whosonfirst.log.error("unable to geocode address: " + address);
			};
			$('#venue-lookup-address').addClass('loading');
			mapzen.whosonfirst.net.fetch(url, onsuccess, onerror);
		},

		select_geocoded: function(lat, lng) {
			self.map.setView([lat, lng], 16);
			var ll = self.map.getCenter();
			self.lookup_hierarchy(ll);
			self.set_property('geom:latitude', ll.lat);
			self.set_property('geom:longitude', ll.lng);
		},

		update_name: function(disable_nearby_check) {
			var name = $('input[name="name"]').val();
			self.set_property('wof:name', name);
			if (! disable_nearby_check) {
				self.check_nearby();
			}
		},

		update_address: function() {
			self.set_property('addr:full', $('textarea[name="address"]').val());
		},

		update_tags: function() {
			var tag_list = $('input[name="tags"]').val();
			tag_list = tag_list.split(',');
			var tags = [];
			for (var i = 0; i < tag_list.length; i++) {
				var t = tag_list[i].trim();
				if (t != '' && tags.indexOf(t) != -1) {
					tags.push(t);
				}
			}
			self.set_property('wof:tags', tags);
		},

		check_nearby: function() {
			$('#venue-response').html('<div class="alert alert-info">Checking for nearby duplicates...</div>');
			var center = self.map.getCenter();
			var method = 'wof.places.get_nearby';
			var args = {
				latitude: center.lat,
				longitude: center.lng,
				placetype: 'venue',
				name: $('input[name="name"]').val(),
				per_page: 250
			};
			var onsuccess = function(rsp) {
				self.show_dupe_candidate(rsp.results);
			};
			var onerror = function(rsp) {
				console.error(rsp);
			};
			mapzen.whosonfirst.boundaryissues.api.api_call(method, args, onsuccess, onerror);
		},

		show_dupe_candidate: function(places, index) {
			if (! index) {
				index = 0;
			}
			if (! self.dupe_candidates) {
				// Cache the dupe candidates for 'next' button
				var dupes = self.get_dupe_candidates(places);
				self.dupe_candidates = dupes;
			} else {
				var dupes = self.dupe_candidates;
			}
			if (dupes.length > 0) {
				var place = dupes[index];
				var wof_id = htmlspecialchars(place['wof:id']);
				var url = mapzen.whosonfirst.boundaryissues.utils.abs_root_urlify('/id/' + wof_id);
				var name = htmlspecialchars(place['wof:name']);
				var dupe_next_btn = '';
				if (dupes.length > 1) {
					dupe_next_btn = '<a href="#" class="btn btn-sm btn-default" id="dupe-next">Try next</a>';
				}
				var dupe_num = '(' + (index + 1) + ' of ' + dupes.length + ')';
				$('#venue-response').html('<div id="dupe-alert" class="alert alert-danger"><p>Does this record exist already? This seems similar to <a href="' + url + '" class="hey-look">' + name + '</a> ' + dupe_num + '</p><p><a href="#" class="btn btn-sm btn-primary" id="dupe-same">Same place</a> ' + dupe_next_btn + ' <a href="#" class="btn btn-sm btn-default" id="dupe-ignore">Not a duplicate</a></p></div>');
				$('#dupe-same').click(function(e) {
					e.preventDefault();
					$('#dupe-alert').remove();
					$('#venue-response').html('<div class="alert alert-info" id="dupe-merged">Saving to server...</div>');
					var success_cb = function() {
						$('#venue form').append('<input type="hidden" name="wof_id" id="wof_id" value="' + wof_id + '">');
						$('#dupe-merged').html('This CSV row will be merged with <a href="' + url + '">the existing record</a>.');
						$('#submit-btn').attr('value', 'Save venue');
						check_for_wof_id();
					}
					self.set_wof_id(wof_id, success_cb);
				});
				$('#dupe-ignore').click(function(e) {
					e.preventDefault();
					$('#venue-response').html('');
				});
				$('#dupe-next').click(function(e) {
					e.preventDefault();
					index++;
					if (index == dupes.length) {
						index = 0;
					}
					self.show_dupe_candidate(places, index);
				});
			} else {
				$('#venue-response').html('');
			}
		},

		get_dupe_candidates: function(places) {

			var name = $('input[name="name"]').val();
			var stop_list = ['the'];
			var words = name.split(' ');
			var nearby = [];
			var ld, total_ld, place, place_words, place_word, word;

			// Yes, that's right, get ready for triple-nested for
			// loop! The short version is: check if any of words in
			// the name are within a levenshtein distance of 3 of
			// any place names near the given lat/lng.
			// (20170331/dphiffer)

			for (var i = 0; i < places.length; i++) {
				ld = 0;
				place = places[i];
				place_words = place['wof:name'].split(' ');
				for (var j = 0; j < place_words.length; j++) {
					place_word = place_words[j].toLowerCase();
					if (stop_list.indexOf(place_word) != -1) {
						continue;
					}
					for (var k = 0; k < words.length; k++) {
						word = words[k].toLowerCase();
						if (stop_list.indexOf(word) != -1) {
							continue;
						}
						ld = levenshteinDistance(word, place_word);
						if (ld < 3) {
							if (typeof place.total_ld == 'undefined') {
								place.total_ld = 0;
								nearby.push(place);
							}
						}
						if (typeof place.total_ld != 'undefined') {
							place.total_ld += ld;
						}
					}
				}
			}
			nearby.sort(function(a, b) {
				if (a.total_ld < b.total_ld) {
					return -1;
				} else if (b.total_ld < a.total_ld) {
					return 1;
				} else {
					return 0;
				}
			});
			return nearby;
		},

		set_wof_id: function(wof_id, success_cb, error_cb) {

			var data = {
				crumb: $('#venue').data('crumb-update-csv'),
				csv_id: $('#csv_id').val(),
				csv_row: $('#csv_row').val(),
				wof_id: wof_id
			};

			var onsuccess = function(rsp) {
				if (success_cb) {
					success_cb(rsp);
				}
			};

			var onerror = function(rsp) {
				if (error_cb) {
					error_cb(rsp);
				}
				mapzen.whosonfirst.log.error("error calling wof.update_csv");
			};

			mapzen.whosonfirst.boundaryissues.api.api_call("wof.update_csv", data, onsuccess, onerror);
		}
	};

	function setup_map(bbox_init) {

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

		if (bbox_init) {
			mapzen.whosonfirst.boundaryissues.bbox.init(map, function(rsp) {
				self.set_country(rsp.country);
				slippymap.crosshairs.init(map);
			});
		}

		map.on('moveend', function() {
			var ll = map.getCenter();
			self.lookup_hierarchy(ll);
			self.set_property('geom:latitude', ll.lat);
			self.set_property('geom:longitude', ll.lng);
		});

		geocoder.on('select', function(e) {
			self.show_feature_pin(map, geocoder, e.feature);
		});
	}

	function setup_form() {

		function onsuccess(id) {
			if ($('#csv_id').length > 0) {
				var csv_id = $('#csv_id').val();
				var csv_row = parseInt($('#csv_row').val());
				var csv_row_count = parseInt($('#csv_row_count').val());
				if (csv_row == csv_row_count) {
					window.location = mapzen.whosonfirst.boundaryissues.utils.abs_root_urlify('/csv/' + csv_id + '/');
				} else {
					window.location = mapzen.whosonfirst.boundaryissues.utils.abs_root_urlify('/csv/' + csv_id + '/' + (csv_row + 1) + '/');
				}
			} else {
				var edit_url = mapzen.whosonfirst.boundaryissues.utils.abs_root_urlify('/id/' + id + '/');
				$('#venue-response').html('<div class="alert alert-success">Your venue has been saved. You can <a href="' + edit_url + '">edit the WOF record</a> or add another venue.</div>');
				self.reset_properties();
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

		$('input[name="name"]').change(self.update_name);
		$('textarea[name="address"]').change(self.update_address);
		$('input[name="tags"]').change(self.update_tags);
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
				self.check_nearby();
			} else {
				self.geocode_address(address);
			}
		});
	}

	function setup_preview() {
		$('#property-preview-link').click(function(e) {
			e.preventDefault();
			$('#property-preview').toggleClass('visible');
			if ($('#property-preview').hasClass('visible')) {
				var html = '<table>';
				var keys = Object.keys(self.properties);
				keys.sort();
				$.each(keys, function(i, key) {
					var value = self.properties[key];
					if (typeof value == 'object') {
						value = JSON.stringify(value);
					}
					html += '<tr>';
					html += '<td class="property">' + htmlspecialchars(key) + '</td>';
					html += '<td class="preview" data-property="' + htmlspecialchars(key) + '">' + htmlspecialchars(value) + '</td>';
					html += '</tr>';
				});
				html += '</table>';
				$('#property-preview').html(html);
			}
		});
	}

	function check_for_wof_id() {

		if ($('#wof_id').length == 0) {
			return false;
		}

		var wof_id = $('#wof_id').val();

		var onsuccess = function(rsp) {
			properties = rsp.properties;
			self.properties = properties;
			var lat = parseFloat(properties['geom:latitude']);
			var lng = parseFloat(properties['geom:longitude']);
			self.map.setView([lat, lng], 16);
			slippymap.crosshairs.init(self.map);
			check_for_assignments("disable nearby check");
			self.update_name("disable nearby check");
			self.update_address();
			self.update_tags();
		};

		var onerror = function(rsp) {
			mapzen.whosonfirst.log.error("unable to load WOF ID " + wof_id);
			$('#dupe-merged').html('<strong>Error</strong> could not find data for that place.');
			$('#dupe-merged').removeClass('alert-info');
			$('#dupe-merged').addClass('alert-danger');
		};

		var path = '/id/' + wof_id + '.geojson';
		var url = mapzen.whosonfirst.boundaryissues.utils.abs_root_urlify(path);
		mapzen.whosonfirst.net.fetch(url, onsuccess, onerror);

		return true;
	}

	function check_for_assignments(disable_nearby_check) {
		var $properties = $('input.property');
		if ($properties.length == 0) {
			return false;
		}
		var assignments = {};
		$properties.each(function(i, input) {

			var key = $(input).attr('name');
			var value = $(input).val();

			if (key == 'edtf:cessation') {
				if (value == '') {
					value = 'uuuu';
				} else {
					self.set_property('mz:is_current', 0);
				}
			}

			assignments[key] = value;

			self.set_property(key, value);
		});

		if (assignments['geom:latitude'] &&
		    assignments['geom:longitude']) {
			var lat = parseFloat(assignments['geom:latitude']);
			var lng = parseFloat(assignments['geom:longitude']);
			self.map.setView([lat, lng], 16);
			self.lookup_hierarchy({
				lat: lat,
				lng: lng
			});
			slippymap.crosshairs.init(self.map);
			if (! disable_nearby_check) {
				self.check_nearby();
			}
		} else if (assignments['addr:full']) {
			self.geocode_address(assignments['addr:full'], function() {
				slippymap.crosshairs.init(self.map);
				if (! disable_nearby_check) {
					self.check_nearby();
				}
			});
		} else {
			console.log('We are on null island');
		}

		return true;
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

			var bbox_init = $('#csv_id').length == 0;
			setup_map(bbox_init);
			setup_form();
			setup_address();
			setup_preview();
			if (! check_for_wof_id()) {
				check_for_assignments();
			}
			self.update_name();
			self.update_address();
			self.update_tags();
		}
	});

	return self;

})();
