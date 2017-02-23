var mapzen = mapzen || {};
mapzen.whosonfirst = mapzen.whosonfirst || {};
mapzen.whosonfirst.boundaryissues = mapzen.whosonfirst.boundaryissues || {};

// for the time being this assumes jQuery is present
// that decision may change some day but not today
// (20160121/thisisaaronland)

// This JS library answers the question: what should the default position of a
// "blank" map be? The answer to that question is chosen, in the order of
// precedence:

// 1. The hash in the URL (i.e., #[zoom]/[lat]/[lng])
// 2. A user-chosen default bbox
// 3. The bbox from the country WOF record for the current IP address

// We ping the IP address service either way, since the caller may be depending
// on its results for other things. (20170223/dphiffer)

mapzen.whosonfirst.boundaryissues.bbox = (function() {

	var self = {
		'init': function(map, cb) {

			var container = map.getContainer();
			var id = container.id;

			var set_bbox = null;
			var hash_view = location.hash.match(/^#(\d+)\/([0-9.-]+)\/([0-9.-]+)/)

			if (hash_view) {
				var zoom = parseInt(hash_view[1]);
				var lat = parseFloat(hash_view[2]);
				var lng = parseFloat(hash_view[3]);
				map.setView([lat, lng], zoom);
				set_bbox = self.get_bbox(map);
			} else if (container.getAttribute('data-default-bbox')) {
				var bbox = container.getAttribute('data-default-bbox');
				bbox = bbox.split(',');
				var swlon = parseFloat(bbox[0]);
				var swlat = parseFloat(bbox[1]);
				var nelon = parseFloat(bbox[2]);
				var nelat = parseFloat(bbox[3]);
				self.set_bbox(map, [swlon, swlat, nelon, nelat]);
				set_bbox = bbox;
			}

			var onerror = function(rsp) {
				mapzen.whosonfirst.log.error("could not load ip service");
			};

			var onsuccess = function(rsp) {
				self.load_country_wof(rsp.country_id, function(rsp) {
					if (! set_bbox) {
						self.set_bbox(map, rsp.bbox);
					}
					if (cb) {
						cb({
							bbox: rsp.bbox,
							country: rsp
						});
					}
				});
			};

			var url = 'https://ip.dev.mapzen.com/?raw=1';
			mapzen.whosonfirst.net.fetch(url, onsuccess, onerror);
		},

		'draw_set_default_link': function(map) {
			var link = document.getElementById("bbox-default");
			if (! link) {
				var link = document.createElement("a");

				link.setAttribute("href", "#");
				link.setAttribute("id", "bbox-default");
				link.innerText = 'set as default view';
				link.addEventListener('click', function(e) {
					e.preventDefault();
					var bbox = self.get_bbox(map);
					self.set_default_bbox(bbox);
				}, false);

				var container = map.getContainer();
				var container_el = document.getElementById(container.id);

				container_el.parentNode.insertBefore(link, container_el.nextSibling);

				map.on('move', function() {
					link.className = '';
				});
			}
		},

		'get_bbox': function(map) {
			var bounds = map.getBounds();
			var swlon = bounds._southWest.lng;
			var swlat = bounds._southWest.lat;
			var nelon = bounds._northEast.lng;
			var nelat = bounds._northEast.lat;
			return [swlon, swlat, nelon, nelat];
		},

		'set_bbox': function(map, bbox) {
			var swlon = bbox[0];
			var swlat = bbox[1];
			var nelon = bbox[2];
			var nelat = bbox[3];
			map.fitBounds([[swlat, swlon], [nelat, nelon]]);
		},

		'set_default_bbox': function(bbox) {
			bbox = bbox.join(',');
			var link = $("#bbox-default");
			var onsuccess = function(rsp) {
				mapzen.whosonfirst.log.info('saved default bbox as ' + bbox);
				link.addClass('bbox-saved');
			};
			var onerror = function(rsp) {
				mapzen.whosonfirst.log.info('could not assign default_bbox user setting');
			};
			mapzen.whosonfirst.boundaryissues.api.api_call('wof.users_settings_set', {
				name: 'default_bbox',
				value: bbox
			}, onsuccess, onerror);
		},

		'load_country_wof': function(id, cb) {

			var base_url = $('body').data('data-abs-root-url');
			var relpath = mapzen.whosonfirst.data.id2relpath(id);
			var url = base_url + relpath;

			var onsuccess = function(rsp) {
				if (cb) {
					cb(rsp);
				}
			};

			var onfailure = function(rsp) {
				mapzen.whosonfirst.log.error('Could not load to country WOF ' + id + '.');
			};

			mapzen.whosonfirst.net.fetch(url, onsuccess, onfailure);
		}
	}

	return self;

})();
