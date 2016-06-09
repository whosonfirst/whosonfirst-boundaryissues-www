var mapzen = mapzen || {};
mapzen.whosonfirst = mapzen.whosonfirst || {};
mapzen.whosonfirst.boundaryissues = mapzen.whosonfirst.boundaryissues || {};

// for the time being this assumes jQuery is present
// that decision may change some day but not today
// (20160121/thisisaaronland)

mapzen.whosonfirst.boundaryissues.results = (function() {

	var map,
	    batch_update_ids = [],
	    VenueIcon,
	    poi_icon_base;

	var self = {

		setup_map: function() {
			var lat = 40.73581157695217;
			var lon = -73.9815902709961;
			var zoom = 12;

		    	var scene = mapzen.whosonfirst.boundaryissues.utils.abs_root_urlify('/tangram/refill.yaml');
			mapzen.whosonfirst.leaflet.tangram.scenefile(scene);

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
				if ($(result).data('icon')) {
					var marker = self.setup_icon_marker(result);
				} else {
					var marker = self.setup_circle_marker(result);
				}

				markers.push(marker);
				marker.on('mouseover', function() {
					this.setStyle(mapzen.whosonfirst.leaflet.styles.venue_hover());
				});
				marker.on('mouseout', function() {
					this.setStyle(this._style);
				});
				marker.on('click', function() {
					var id = $(result).data('id');
					var url = '/id/' + parseInt(id) + '/';

					location.href = mapzen.whosonfirst.boundaryissues.utils.abs_root_urlify(url);
				});
				marker.bindLabel($(result).find('a').html());
			});
			var group = L.featureGroup(markers).addTo(map);
			map.fitBounds(group.getBounds());
		},

		setup_circle_marker: function(result) {
			var marker_style = self.get_venue_marker_style(result);
			var id = $(result).data('id');
			var marker = L.circleMarker({
				lat: parseFloat($(result).data('lat')),
				lng: parseFloat($(result).data('lng'))
			}, marker_style);
			marker._style = marker_style;
			return marker;
		},

		setup_icon_marker: function(result) {
			var id = $(result).data('id');
			var icon_id = $(result).data('icon');
			var options = {
				icon: L.icon({
					iconRetinaUrl: poi_icon_base + icon_id + '.png',
					iconSize: [19, 19],
					iconAnchor: [9, 9],
					popupAnchor: [-3, -38]
				})
			}
			var marker = L.marker({
				lat: parseFloat($(result).data('lat')),
				lng: parseFloat($(result).data('lng'))
			}, options);
			return marker;
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

				location.href = mapzen.whosonfirst.boundaryissues.utils.abs_root_urlify(url);
			});
		},

		setup_results: function() {
			var lastCheckbox;
			$('#search-results > li > input[type=checkbox]').each(function(i, checkbox) {
				// Check for initial checked state (i.e., reload after selection)
				self.update_batch(checkbox);
				$(checkbox).data('checkbox-num', i);
				$(checkbox).change(function(e) {
					// Check for change to checked state
					self.update_batch(e.target);
				});
				$(checkbox).click(function(e) {
					if (typeof lastCheckbox == 'undefined') {
						lastCheckbox = $(e.target).data('checkbox-num');
						return;
					}
					if (! e.shiftKey) {
						lastCheckbox = $(e.target).data('checkbox-num');
						return;
					}
					var currCheckbox = $(e.target).data('checkbox-num');
					if (currCheckbox > lastCheckbox) {
						var min = lastCheckbox;
						var max = currCheckbox;
					} else {
						var min = currCheckbox;
						var max = lastCheckbox;
					}
					$('#search-results > li > input[type=checkbox]').each(function(j, cb) {
						var num = $(cb).data('checkbox-num');
						if (num > min && num < max) {
							cb.checked = e.target.checked;
							self.update_batch(cb);
						}
					});
					self.update_batch(e.target);
					lastCheckbox = currCheckbox;
				});
			});
			$('#batch-update-status a').click(function(e) {
				e.preventDefault();
				if (batch_update_ids.length == 0) {
					return;
				}
				var data = {
					crumb: $('#batch-update').data('crumb-save-batch'),
					ids: batch_update_ids.join(',')
				};
				if (e.target.nodeName == 'A') {
					var $link = $(e.target);
				} else {
					var $link = $(e.target).closest('a');
				}
				var status = $link.data('status');

				var today = mapzen.whosonfirst.boundaryissues.edit.get_edtf_date(new Date());
				if (status == 'current') {
					data.properties = {
						"mz:is_current": 1,
						"edtf:cessation": 'uuuu',
						"edtf:deprecated": 'uuuu'
					};
				} else if (status == 'closed') {
					data.properties = {
						"mz:is_current": 0,
						"edtf:cessation": today
					};
				} else if (status == 'deprecated') {
					data.properties = {
						"mz:is_current": 0,
						"edtf:deprecated": today
					};
				} else if (status == 'funky') {
					data.properties = {
						"mz:is_funky": 1
					};
				}
				data.properties = JSON.stringify(data.properties);

				var onsuccess = function(rsp) {
					var properties = rsp.properties;
					$.each(rsp.saved, function(i, wof) {
						var id = wof.properties['wof:id'];
						var $checkbox = $('input[name="select-' + id + '"]');
						var $item = $checkbox.closest('li');
						$checkbox.attr('checked', false);
						$item.removeClass('iscurrent-yes');
						$item.removeClass('iscurrent-no');
						$item.removeClass('iscurrent-unknown');
						$item.removeClass('deprecated');
						if (properties['mz:is_current']) {
							$item.addClass('iscurrent-yes');
						} else if (properties['mz:is_funky']) {
							// We don't do anything with is_funky yet
						} else if (properties['edtf:deprecated']) {
							$item.addClass('iscurrent-no');
							$item.addClass('deprected');
						} else if (properties['edtf:cessation']) {
							$item.addClass('iscurrent-no');
						} else {
							// In theory we should never be uncertain at this point
							$item.addClass('iscurrent-unknown');
						}
					});
				};
				var onerror = function(rsp) {
					mapzen.whosonfirst.log.debug("error with batch saving.");
					console.log(rsp);
				};

				mapzen.whosonfirst.boundaryissues.api.api_call("wof.save_batch", data, onsuccess, onerror);
			});

			$('#batch-update-category a').click(function(e) {
				e.preventDefault();
				if (batch_update_ids.length == 0) {
					return;
				}
				var data = {
					crumb: $('#batch-update').data('crumb-save-batch'),
					ids: batch_update_ids.join(',')
				};
				if (e.target.nodeName == 'A') {
					var $link = $(e.target);
				} else {
					var $link = $(e.target).closest('a');
				}
				var category = $link.data('category');

				var today = mapzen.whosonfirst.boundaryissues.edit.get_edtf_date(new Date());
				data.properties = {
					"wof:category": category
				};
				data.properties = JSON.stringify(data.properties);

				var onsuccess = function(rsp) {
					var properties = rsp.properties;
					$.each(rsp.saved, function(i, wof) {
						var id = wof.properties['wof:id'];
						var $checkbox = $('input[name="select-' + id + '"]');
						var $item = $checkbox.closest('li');
						$checkbox.attr('checked', false);
					});
				};
				var onerror = function(rsp) {
					mapzen.whosonfirst.log.debug("error with batch saving.");
				};
				mapzen.whosonfirst.boundaryissues.api.api_call("wof.save_batch", data, onsuccess, onerror);
			});

			$('#toggle-all').change(function(e) {
				$('.search-result > input[type=checkbox]').each(function(i, input) {
					input.checked = $('#toggle-all').get(0).checked;
					self.update_batch(input);
				});
			});

			$('#per-page a').click(function(e) {
				e.preventDefault();
				if (e.target.nodeName == 'A') {
					var $link = $(e.target);
				} else {
					var $link = $(e.target).closest('a');
				}
				var per_page = $link.data('per-page');
				if (location.search.match(/per_page=\d+/)) {
					var q = location.search.replace(/per_page=\d+/, 'per_page=' + per_page);
				} else if (location.search) {
					var q = location.search + '&per_page=' + per_page;
				} else {
					var q = '?per_page=' + per_page;
				}
				window.location = location.pathname + q;
			});

			$('#batch-download').click(function(e) {
				e.preventDefault();
				if (batch_update_ids.length == 0) {
					return;
				}
				var ids = batch_update_ids.join(',');
				var form = document.createElement('form');
				var input = document.createElement('input');
				input.name = 'ids';
				input.value = ids;
				form.appendChild(input.cloneNode());
				form.method = 'POST';
				form.action = '/geojson.php';
				document.body.appendChild(form);
				form.submit();
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
		},

		update_batch: function(checkbox) {
			var id = $(checkbox).closest('li').data('id');
			id = parseInt(id);
			var index = batch_update_ids.indexOf(id);
			if (checkbox.checked) {
				if (index == -1) {
					batch_update_ids.push(id);
				}
			} else {
				if (index != -1) {
					batch_update_ids.splice(index, 1);
				}
			}
			if (batch_update_ids.length > 0) {
				$('#batch-update li').removeClass('disabled');
				$('#batch-download').removeClass('disabled');
			} else {
				$('#batch-update li').addClass('disabled');
				$('#batch-download').addClass('disabled');
			}
		}

	};

	$(document).ready(function() {
		if ($('#search-results').length == 0) {
			return;
		}

		// We need to wait until the page has loaded before we can make
		// calls to mapzen.whosonfirst.boundaryissues.utils.abs_root_urlify.
		// (20160606/dphiffer)

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

		poi_icon_base = mapzen.whosonfirst.boundaryissues.utils.abs_root_urlify('/images/categories/');

		self.setup_map();
		self.setup_drawing();
		self.setup_results();
	});

	return self;
})();
