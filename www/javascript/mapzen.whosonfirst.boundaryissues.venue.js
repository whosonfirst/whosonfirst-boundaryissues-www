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

			$('#venue #parent').html('Loading...');
			self.looking_up_hierarchy = true;

			var data = {
				latitude: ll.lat,
				longitude: ll.lng,
				placetype: 'venue'
			};

			var onsuccess = function(rsp) {
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
					var url = mapzen.whosonfirst.boundaryissues.utils.abs_root_urlify('/id/' + rsp.parents[0].Id);
					var link = '<a href="' + url + '">' + rsp.parents[0].Name + '</a> (' + rsp.parents[0].Placetype + ')';
					$('#venue #parent').html(link);
				} else {
					self.set_property('wof:parent_id', -1);
					$('#venue #parent').html('<i>Unknown parent</i>');
				}
				if (rsp.hierarchy) {
					self.set_property('wof:hierarchy', rsp.hierarchy);
				} else {
					self.set_property('wof:hierarchy', []);
				}

				self.looking_up_hierarchy = false;
				if (self.on_loaded_hierarchy) {
					self.on_loaded_hierarchy();
				}
			}

			var onerror = function(rsp) {
				self.looking_up_hierarchy = false;
				mapzen.whosonfirst.log.error('Error reverse geocoding.');
			};

			mapzen.whosonfirst.boundaryissues.api.api_call("wof.pip", data, onsuccess, onerror);
		},

		update_coordinates: function() {
			var ll = self.map.getCenter();
			self.lookup_hierarchy(ll);
			self.set_property('geom:latitude', ll.lat);
			self.set_property('geom:longitude', ll.lng);
		},

		show_feature_pin: function(map, geocoder, feature) {
			var html = '<a href="#" class="btn btn-primary" id="geocoder-marker-select">Use this result</a> <a href="#" class="btn" id="geocoder-marker-cancel">Cancel</a>';
			var popup = geocoder.marker.bindPopup(html).openPopup();
			var props = feature.properties;
			var ll = geocoder.marker.getLatLng();
			if (feature.bbox) {
				mapzen.whosonfirst.boundaryissues.bbox.set_bbox(map, feature.bbox);
			} else {
				map.panTo(ll);
			}
			$('#geocoder-marker-select').click(function(e) {
				e.preventDefault();
				popup.closePopup();
				geocoder.collapse();
				map.removeLayer(geocoder.marker);
				map.setView(ll, 16);
				self.set_property('geom:latitude', ll.lat);
				self.set_property('geom:longitude', ll.lng);
				//self.lookup_hierarchy(ll.lat, ll.lng);
				//self.update_coordinates(ll, true);
				//self.set_marker(geocoder.marker);

				var address = self.get_geocoded_address(feature);
				if (address != '') {
					$('textarea[name="address"]').val(address);
					self.set_property('addr:full', address);
				}

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

					var address = self.get_geocoded_address(rsp.features[0]);
					if (address != '') {
						$('textarea[name="address"]').val(address);
						self.set_property('addr:full', address);
					}
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
						var address = self.get_geocoded_address(f);
						html += '<li class="list-group-item"><a href="#" data-lat="' + htmlspecialchars(lat) + '" data-lng="' + htmlspecialchars(lng) + '" data-address="' + htmlspecialchars(address) + '" class="geocoded">' + htmlspecialchars(label) + '</a></li>';
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

						var address = $(e.target).data('address');
						if (address != '') {
							$('textarea[name="address"]').val(address);
							self.set_property('addr:full', address);
						}

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

		get_geocoded_address: function(feature) {

			// This is a US-centric way of encoding an address
			// (20170625/dphiffer)

			var props = feature.properties;
			var regex = new RegExp('^' + props.name);
			if (props.housenumber && props.street) {
				return props.label.replace(regex, props.housenumber + ' ' + props.street);
			} else {
				return '';
			}
		},

		select_geocoded: function(lat, lng) {
			self.map.setView([lat, lng], 16);
			var ll = self.map.getCenter();
			self.lookup_hierarchy(ll);
			self.set_property('geom:latitude', ll.lat);
			self.set_property('geom:longitude', ll.lng);
		},

		update_name: function() {
			console.log('update_name');
			var name = $('input[name="name"]').val();
			self.set_property('wof:name', name);
			self.check_nearby();
		},

		update_address: function() {
			console.log('update_address');
			var address = $('textarea[name="address"]').val();
			if (address != '') {
				self.set_property('addr:full', address);
			} else {
				delete self.properties['wof:address'];
			}
		},

		update_tags: function() {
			console.log('update_tags');
			var tag_list = $('input[name="tags"]').val();
			tag_list = tag_list.split(',');
			var tags = [];
			for (var i = 0; i < tag_list.length; i++) {
				var t = tag_list[i].trim();
				if (t != '' && tags.indexOf(t) == -1) {
					tags.push(t);
				}
			}
			self.set_property('wof:tags', tags);
		},

		check_nearby: function() {

			if (self.disable_nearby_check) {
				return false;
			}

			var name = $('input[name="name"]').val();
			if (name == '') {
				return false;
			}

			$('#venue-response').html('<div class="alert alert-info">Checking for nearby duplicates...</div>');
			var center = self.map.getCenter();
			var method = 'wof.places.get_nearby';
			var args = {
				latitude: center.lat,
				longitude: center.lng,
				placetype: 'venue',
				name: name,
				per_page: 250
			};
			var onsuccess = function(rsp) {
				self.dupe_candidates = null;
				self.show_dupe_candidate(rsp.results);
			};
			var onerror = function(rsp) {
				$('#venue-response').html('<div class="alert alert-danger">Oops, there was a problem checking for duplicates.</div>');
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

				if (! place['wof:id'] ||
				    ! place['wof:name']) {
					$('#venue-response').html('<div class="alert alert-danger">Oops, there was a problem retrieving the duplicate records.</div>');
					return;
				}

				var wof_id = htmlspecialchars(place['wof:id']);
				var url = mapzen.whosonfirst.boundaryissues.utils.abs_root_urlify('/id/' + wof_id);
				var name = htmlspecialchars(place['wof:name']);

				var dupe_same_btn = '<div class="btn-group">' +
					'<a href="#" class="btn btn-sm btn-primary" id="dupe-same">Same place</a>' +
					'<button type="button" class="btn btn-sm btn-primary dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">' +
					'<span class="caret"></span>' +
					'<span class="sr-only">Toggle Dropdown</span>' +
					'</button>' +
					'<ul class="dropdown-menu">' +
					'<li><a href="#" id="dupe-assign">Assign dupe by WOF ID...</a></li>' +
					'</ul>' +
					'</div>';

				var dupe_next_btn = '';
				if (dupes.length > 1) {
					dupe_next_btn = '<a href="#" class="btn btn-sm btn-default" id="dupe-next">Try next</a>';
				}

				var dupe_assignment_callback = function() {
					$('#venue form').append('<input type="hidden" name="wof_id" id="wof_id" value="' + wof_id + '">');
					$('#dupe-merged').html('Your edits will be merged into <a href="' + url + '">the existing record</a>. [<a href="#" id="dupe-undo">undo</a>]');
					$('#submit-btn').attr('value', 'Save venue');

					// TODO: do something more sophisticated here, taking
					// the selected property selection into account. Right
					// now we just clobber the existing value with the one
					// from the merge target.
					// (20170430/dphiffer)
					$('input[name="name"]').val(name);
					if ('addr:full' in place) {
						$('input[name="address"]').val(htmlspecialchars(place['addr:full']));
					}

					if ('wof:tags' in place) {
						$('input[name="tags"]').val(htmlspecialchars(place['wof:tags']));
					}

					check_for_wof_id();
					$('#dupe-undo').click(function(e) {
						e.preventDefault();
						self.set_wof_id(-1, function(rsp) {
							if (rsp.updated &&
							    rsp.updated.indexOf('wof_ids') !== -1) {
								$('#venue-response').html('');
								var assignments = check_for_assignments();
								if (assignments['wof:name']) {
									$('input[name="name"]').val(assignments['wof:name']);
								}
								if (assignments['addr:full']) {
									$('textarea[name="address"]').val(assignments['addr:full']);
								}
								if (assignments['wof:tags']) {
									$('input[name="tags"]').val(assignments['wof:tags']);
								}
								self.disable_nearby_check = false;
								self.check_nearby();
							}
						});
					});
				};

				var dupe_num = '(' + (index + 1) + ' of ' + dupes.length + ')';
				$('#venue-response').html('<div id="dupe-alert" class="alert alert-danger"><p>Does this record exist already? This seems similar to:<br><a href="' + url + '" class="hey-look">' + name + '</a> ' + dupe_num + '</p><p>' + dupe_same_btn + ' ' + dupe_next_btn + ' <a href="#" class="btn btn-sm btn-default" id="dupe-ignore">Not a dupe</a></p></div>');
				$('#dupe-same').click(function(e) {
					e.preventDefault();
					self.set_wof_id(wof_id, dupe_assignment_callback);
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
				$('#dupe-assign').click(function(e) {
					e.preventDefault();
					var user_wof_id = prompt('Which WOF ID should we merge into?');
					user_wof_id = parseInt(user_wof_id);
					if (user_wof_id) {
						wof_id = user_wof_id;
						url = mapzen.whosonfirst.boundaryissues.utils.abs_root_urlify('/id/' + wof_id);
						var onerror = function() {
							mapzen.whosonfirst.log.error("Error fetching WOF record");
						};
						mapzen.whosonfirst.net.fetch(url + '.geojson', function(rsp) {
							var props = rsp.properties;
							name = props['wof:name'];
							self.set_wof_id(wof_id, dupe_assignment_callback);
							self.set_property('wof:hierarchy', props['wof:hierarchy']);
							self.set_property('wof:parent_id', props['wof:parent_id']);
						}, onerror);
					}
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

			if ($('#csv_id').val() == '') {
				success_cb();
			}

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

			$('#dupe-alert').remove();
			$('#venue-response').html('<div class="alert alert-info" id="dupe-merged">Talking to the server...</div>');

			mapzen.whosonfirst.boundaryissues.api.api_call("wof.update_csv", data, onsuccess, onerror);
		}
	};

	function setup_map(bbox_init) {

		var scene = mapzen.whosonfirst.boundaryissues.utils.abs_root_urlify('/tangram/refill.yaml');
		mapzen.whosonfirst.leaflet.tangram.scenefile(scene);

		// For now we just assume everyone using BI can read English. This
		// should be configurable. (20170704/dphiffer)
		mapzen.whosonfirst.leaflet.tangram.scene_options({
			ux_language: 'en'
		});

		var map = mapzen.whosonfirst.leaflet.tangram.map_with_prefs('map', 'map_prefs', function(map, prefs) {

			self.map = map;
			map.on('moveend', self.update_coordinates);

			var hash = new L.Hash(map);

			L.control.locate().addTo(map);

			if (bbox_init) {
				mapzen.whosonfirst.boundaryissues.bbox.init(map);
			}

			slippymap.crosshairs.init(map, {
				css: {
					'width': '25px',
					'height': '41px',
					'background': 'url("/images/marker-icon-2x.png")',
					'background-size': '25px 41px',
					'margin-left': '-12px',
					'margin-top': '-41px'
				}
			});
		});

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
	}

	function setup_form() {

		function onsuccess(id) {
			if ($('#csv_id').val() != "") {
				var csv_id = $('#csv_id').val();
				var csv_row = parseInt($('#csv_row').val());
				var csv_row_count = parseInt($('#csv_row_count').val());
				if (csv_row == csv_row_count) {
					var redirect = mapzen.whosonfirst.boundaryissues.utils.abs_root_urlify('/csv/' + csv_id + '/');
				} else {
					var redirect = mapzen.whosonfirst.boundaryissues.utils.abs_root_urlify('/csv/' + csv_id + '/' + (csv_row + 1) + '/');
				}
				window.location = redirect;
			} else {
				var edit_url = mapzen.whosonfirst.boundaryissues.utils.abs_root_urlify('/id/' + id + '/');
				$('#venue-response').html('<div class="alert alert-success">Your venue has been saved. You can <a href="' + edit_url + '">edit the WOF record</a> or add another venue.</div>');
				self.reset_properties();
				window.scrollTo(0, 0);
				if (self.on_save) {
					self.on_save();
				}
			}
		}

		function onerror(rsp) {
			var message = '😿 There was a problem saving your venue.';
			if (rsp.error && rsp.error.message) {
				message = '<strong>Oops!</strong> ' + htmlspecialchars(rsp.error.message);
			}
			$('#venue-response').html('<div class="alert alert-danger">' + message + '</div>');
		}

		function save_venue() {
			$('#venue-response').html('<div class="alert alert-info">Saving venue...</div>');
			var geojson = self.generate_geojson();
			self.save_to_server(geojson, onsuccess, onerror);
		}

		$('#venue form').submit(function(e) {

			e.preventDefault();

			var parent_id = self.properties['wof:parent_id'];
			var hierarchy = self.properties['wof:hierarchy'];
			if ($('input[name="name"]').val() == '') {
				$('#venue-response').html('<div class="alert alert-warning">Oops, you forgot to enter a name for your venue.</div>');
			} else if (! ('wof:hierarchy' in self.properties)) {
				self.update_coordinates();
				$('#venue-response').html('<div class="alert alert-warning">Oops, the hierarchy has not been assigned. Try again in a moment?</div>');
			} else if (! ('wof:parent_id' in self.properties)) {
				self.update_coordinates();
				$('#venue-response').html('<div class="alert alert-warning">Oops, the parent WOF ID has not been assigned. Try again in a moment?</div>');
			} else if (self.looking_up_hierarchy) {
				self.on_loaded_hierarchy = save_venue;
			} else {
				save_venue();
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

	var geotagged_index;
	var geotagged_num;

	function setup_geotagged() {
		var geotagged_id = location.search.match(/geotagged_\d+/);
		if (! geotagged_id) {
			return;
		}
		geotagged_id = geotagged_id[0];
		mapzen.whosonfirst.geotagged.load_index(function(index) {
			geotagged_index = index;
			for (var i = 0; i < index.geotagged_ids.length; i++) {
				if (index.geotagged_ids[i] == geotagged_id) {
					geotagged_num = i;
				}
			}
			if (typeof geotagged_num != 'number') {
				var url = mapzen.whosonfirst.boundaryissues.utils.abs_root_urlify('/geotagged/');
				var header = '<a href="' + url + '">Geotagged photo</a><br>';
				$('#venue-geotagged').html(header + 'Could not find the specified geotagged photo :(');
				$('#venue-geotagged').removeClass('hidden');
				return;
			}
			show_geotagged(geotagged_num);
		});
	}

	function show_geotagged(num) {

		// Oh hey, let's talk about iOS & EXIF orientation modes!
		//
		// Yes, browser sniffing is bad and shameful, but iOS insists on
		// behaving differently from other OS's when it comes to EXIF
		// orientation modes. Arguably its behavior is the "correct" one
		// (it auto-rotates images compensating for the EXIF orientation
		// mode) but ... NO OTHER OS OR BROWSER DOES THAT. So iOS gets
		// the sniff treatment. To complicate things a little more, iOS
		// only rotates <img> tags, not background images. Go figure.
		//
		// Based on: https://stackoverflow.com/a/9039885/937170
		// See also: https://phiffer.org/etc/exif-orientation-test/
		//
		// (20170724/dphiffer)

		function is_ios() {

			var iDevices = [
				'iPad Simulator',
				'iPhone Simulator',
				'iPod Simulator',
				'iPad',
				'iPhone',
				'iPod'
			];

			if (!! navigator.platform) {
				while (iDevices.length) {
					if (navigator.platform === iDevices.pop()) {
						return true;
					}
				}
			}

			return false;
		}

		geotagged_num = num;
		var id = geotagged_index.geotagged_ids[geotagged_num];
		var geotagged_count = geotagged_index.geotagged_ids.length;
		mapzen.whosonfirst.geotagged.load_photo(id, function(photo) {

			if ($(window).width() < 480) {
				$('#venue-geotagged').addClass('one-column');
			}

			var url = mapzen.whosonfirst.boundaryissues.utils.abs_root_urlify('/geotagged/');
			var header = '<a href="' + url + '">Geotagged photo</a><br>';
			var nav = '<span id="venue-geotagged-prev"><small class="glyphicon glyphicon-chevron-left"></small> prev</span>';
			nav += '<span id="venue-geotagged-index">' + (geotagged_count - geotagged_num) + ' of ' + geotagged_count + '</span>';
			nav += '<span id="venue-geotagged-next">next <small class="glyphicon glyphicon-chevron-right"></small></span>';

			if (geotagged_count == 1) {
				nav = '';
			}

			if (photo.geotags) {
				var ll = [photo.geotags.latitude, photo.geotags.longitude];
				self.map.setView(ll, 17);
				self.update_coordinates();
				var status = '<small>' + photo.geotags.latitude.toFixed(6) + ', ' + photo.geotags.longitude.toFixed(6) + '</small>';
			} else {
				var status = '<i>No geotags found in photo</i><br>';
			}
			status = '<div id="venue-geotagged-status">' + status + '</div>';

			var orientation = photo.orientation;
			if (is_ios()) {

				// See comment above about iOS & EXIF orientation modes.

				orientation = '';
			}

			var img_tag = '<img src="' + photo.data_uri + '" class="geotagged thumbnail ' + orientation + '">';
			var img = '<div id="venue-geotagged-img">' + img_tag + '</div>';
			var controls = '<div id="venue-geotagged-controls">' + header + nav + '</div><br class="clear">';
			var zoom_close = '<div id="venue-geotagged-zoom-close"><i class="fa fa-close" aria-hidden="true"></i></div>';
			var zoom = '<div id="venue-geotagged-zoom" class="hidden"><div id="venue-geotagged-zoom-relative">' + img_tag + zoom_close + '</div></div>';

			$('#venue-geotagged').html(img + controls + status + zoom);
			$('#venue-geotagged').removeClass('hidden');
			$('#venue-geotagged-zoom img').removeClass('thumbnail');

			$('#venue-geotagged-img').click(function(e) {
				$(document.body).addClass('no-scroll');
				$('#venue-geotagged-zoom').removeClass('hidden');
				var h = $('#venue-geotagged-zoom img').height();
				var wh = $(window).height();
				if (h < wh) {
					$('#venue-geotagged-zoom img').css('margin-top', (wh - h) / 2);
				}
			});

			$('#venue-geotagged-zoom-close').click(function() {
				$('#venue-geotagged-zoom').addClass('hidden');
				$(document.body).removeClass('no-scroll');
			});

			var ratio;
			if (photo.exif) {
				ratio = photo.exif.PixelXDimension / photo.exif.PixelYDimension;
			}
			var w = $('#venue-geotagged-img').width();

			if (is_ios()) {

				// See comment above about iOS & EXIF orientation modes.

				$('#venue-geotagged-img, #venue-geotagged-controls').css('height', 'auto');
				$('#venue-geotagged').addClass('static');
			} else if (photo.orientation && ratio) {
				if (photo.orientation.indexOf('rotate-90') != -1) {
					var h = Math.round(w * ratio);
					$('#venue-geotagged-img img').css('transform', 'translate3d(-50%, -50%, 0) rotate(90deg) scale(' + ratio + ')');
				} else if (photo.orientation.indexOf('rotate-180') != -1) {
					var h = Math.round(w / ratio);
					$('#venue-geotagged-img img').css('transform', 'translate3d(-50%, -50%, 0) rotate(180deg)');
				} else if (photo.orientation.indexOf('rotate-270') != -1) {
					var h = Math.round(w * ratio);
					$('#venue-geotagged-img img').css('transform', 'translate3d(-50%, -50%, 0) rotate(270deg) scale(' + ratio + ')');
				}
			} else if (ratio) {
				var h = Math.round(w / ratio);
				$('#venue-geotagged-img img').css('transform', 'translate3d(-50%, -50%, 0)');
			}

			if (h) {
				$('#venue-geotagged-img').css('height', h);
				$('#venue-geotagged').removeClass('static');
				if (! $('#venue-geotagged').hasClass('one-column')) {
					$('#venue-geotagged-controls').css('height', h);
				}
			} else {
				$('#venue-geotagged-img, #venue-geotagged-controls').css('height', 'auto');
				$('#venue-geotagged').addClass('static');
			}
			if (geotagged_num > 0) {
				$('#venue-geotagged-next').addClass('active');
			}
			if (geotagged_num < geotagged_count - 1) {
				$('#venue-geotagged-prev').addClass('active');
			}
			$('#venue-geotagged-next').click(function(e) {
				if (! $('#venue-geotagged-next').hasClass('active')) {
					return;
				}
				show_geotagged(geotagged_num - 1);
				update_geotagged_url();
			});
			$('#venue-geotagged-prev').click(function(e) {
				if (! $('#venue-geotagged-prev').hasClass('active')) {
					return;
				}
				show_geotagged(geotagged_num + 1);
				update_geotagged_url();
			});
		});

		function update_geotagged_url() {
			var id = geotagged_index.geotagged_ids[geotagged_num];
			var state = {
				geotagged_id: id
			};
			var url = '?' + id + location.hash;
			history.pushState(state, document.title, url);
		}

		self.on_save = function() {
			if (geotagged_num < geotagged_count - 1) {
				show_geotagged(geotagged_num + 1);
			}
		};
	}

	function check_for_wof_id() {

		if ($('#wof_id').length == 0) {
			return false;
		}

		self.disable_nearby_check = true;
		var wof_id = $('#wof_id').val();

		var onsuccess = function(rsp) {
			properties = rsp.properties;
			self.properties = properties;
			var lat = parseFloat(properties['geom:latitude']);
			var lng = parseFloat(properties['geom:longitude']);
			self.map.setView([lat, lng], 16);
			check_for_assignments();
			self.update_name();
			self.update_address();
			self.update_tags();
		};

		var onerror = function(rsp) {
			mapzen.whosonfirst.log.error("unable to load WOF ID " + wof_id + ' from local repo');
			$('#dupe-merged').html('<strong>Error</strong> could not find data for WOF ID ' + wof_id + '.');
			$('#dupe-merged').removeClass('alert-info');
			$('#dupe-merged').addClass('alert-danger');
		};

		var path = '/id/' + wof_id + '.geojson';
		var url = mapzen.whosonfirst.boundaryissues.utils.abs_root_urlify(path);
		mapzen.whosonfirst.net.fetch(url, onsuccess, onerror);

		return true;
	}

	function check_for_assignments() {
		var $properties = $('input.property');
		if ($properties.length == 0) {
			return null;
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
			} else if (key.substr(0, 13) == 'Concordance: ') {
				var concordance_id = key.substr(13);
				key = 'wof:concordances';
				var concordances = self.properties[key];
				if (! concordances) {
					concordances = {};
				}
				concordances[concordance_id] = value;
				value = concordances;
			} else if (key.substr(0, 8) == 'Custom: ') {
				key = key.substr(8);
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
			self.check_nearby();
		} else if (assignments['addr:full']) {
			self.geocode_address(assignments['addr:full'], function() {
				self.check_nearby();
			});
		} else if ($('textarea[name="address"]').val() != '') {
			var addr = $('textarea[name="address"]').val();
			self.geocode_address(addr, function() {
				self.check_nearby();
			});
		} else {
			console.log('We are on null island');
		}

		return assignments;
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

			var bbox_init = ($('#csv_id').val() == "");
			setup_map(bbox_init);
			setup_form();
			setup_address();
			setup_preview();
			setup_geotagged();
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
