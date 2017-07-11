var mapzen = mapzen || {};
mapzen.whosonfirst = mapzen.whosonfirst || {};

mapzen.whosonfirst.geotagged = (function() {

	var _reader = new FileReader(),
	    _queue = [],
	    _pending = null,
	    _file = null;

	var self = {

		data: [],

		init: function(files, onsuccess, onerror) {
			_queue = files;
			_queue.reverse();
			_reader.onload = self.handle_data_uri;
			if (onsuccess) {
				self.onsuccess = onsuccess;
			}
			if (onerror) {
				self.onerror = onerror;
			}
			if (! _queue || _queue.length == 0) {
				self.onerror('No geotagged photos found');
			} else {
				self.process_next_file();
			}
		},

		// This is a stub, meant to be overridden
		onsuccess: function(msg) {
			console.log(msg);
		},

		// This is a stub, meant to be overridden
		onerror: function(msg) {
			console.error(msg);
		},

		save_to_localforage: function(geotagged, cb) {
			localforage.getItem('geotagged_index').then(function(rsp) {
				var id = (new Date()).getTime();
				var count = geotagged.length;
				if (! rsp) {
					var index = [];
				} else {
					var index = rsp;
				}
				index.push({
					id: id,
					count: count
				});
				var i = 0;
				var save_item = function() {
					localforage.setItem('geotagged_' + id + '_' + i, geotagged[i]).then(function() {
						if (i < count) {
							i++;
							save_item();
						} else {
							cb(id);
						}
					});
				};
				localforage.setItem('geotagged_index', index).then(save_item);
			});
		},

		load_from_localforage: function(id, cb) {
			localforage.getItem('geotagged_index').then(function(index) {
				for (var i = 0; i < index.length; i++) {
					if (index[i].id == id) {
						geotagged = index[i];
						geotagged.item = function(index, cb) {
							localforage.getItem('geotagged_' + this.id + '_' + index).then(cb);
						};
						cb(geotagged);
					}
				}
				cb(null);
			});
		},

		process_next_file: function() {
			if (_queue.length == 0) {
				self.onsuccess(self.data, self.index);
			} else {
				_file = _queue.shift();
				_pending = {
					filename: _file.name,
					geotags: null,
					data_uri: null,
					orientation: null
				};
				_reader.onload = self.handle_array_buffer;
				_reader.readAsArrayBuffer(_file);
			}
		},

		handle_array_buffer: function(e) {
			var data = EXIF.readFromBinaryFile(e.target.result);
			if (data.GPSLatitude &&
			    data.GPSLatitudeRef &&
			    data.GPSLongitude &&
			    data.GPSLongitudeRef) {
				_pending.geotags = self.parse_geotags(data);
			}
			if (data.Orientation) {
				_pending.orientation = self.parse_orientation(data);
			}
			_pending.exif = data;
			_reader.onload = self.handle_data_uri;
			_reader.readAsDataURL(_file);
		},

		// Adapted from https://stackoverflow.com/a/2572991/937170
		parse_geotags: function(data) {
			var lat = self.parse_exif_geotag(data.GPSLatitude, data.GPSLatitudeRef);
			var lng = self.parse_exif_geotag(data.GPSLongitude, data.GPSLongitudeRef);
			var geotags = {
				latitude: lat,
				longitude: lng
			};
			if (data.GPSAltitude && data.GPSAltitudeRef) {

				// altitude is in meters
				geotags.altitude = parseFloat(data.GPSAltitude);

				// if ref is 0: altitude is above sea level
				// if ref is 1: altitude is below sea level
				if (data.GPSAltitudeRef) {
					geotags.altitude = -geotags.altitude;
				}
			}
			return geotags;
		},

		parse_exif_geotag: function(coord, hemi) {
			var degrees = coord.length > 0 ? self.parse_coord(coord[0]) : 0;
			var minutes = coord.length > 1 ? self.parse_coord(coord[1]) : 0;
			var seconds = coord.length > 1 ? self.parse_coord(coord[2]) : 0;

			var flip = (hemi == 'W' || hemi == 'S') ? -1 : 1;

			return flip * (degrees + minutes / 60 + seconds / 3600);
		},

		parse_coord: function(coord) {
			coord = '' + coord;
			var parts = coord.split('/');

			if (parts.length == 0) {
				return 0;
			}

			if (parts.length == 1) {
				return parseFloat(parts[0]);
			}

			return parseFloat(parts[0]) / parseFloat(parts[1]);
		},

		parse_orientation: function(data) {
			switch (data.Orientation) {
				case 1:
					return '';
				case 2:
					return 'flip-horiz';
				case 3:
					return 'rotate-180';
				case 4:
					return 'flip-horiz rotate-180';
				case 5:
					return 'flip-horiz rotate-90';
				case 6:
					return 'rotate-90';
				case 7:
					return 'flip-horiz rotate-270';
				case 8:
					return 'rotate-270';
			}
		},

		handle_data_uri(e) {
			_pending.data_uri = e.target.result;
			self.data.push(_pending);
			_pending = null;
			_file = null;
			self.process_next_file();
		},

		reset_localforage: function(target) {
			localforage.getItem('geotagged_index').then(function(index) {
				if (! index) {
					return;
				}
				for (var i = 0; i < index.length; i++) {
					var geotagged = index[i];
					if (! target || target == geotagged.id) {
						for (var j = 0; j < geotagged.count; j++) {
							var item = 'geotagged_' + geotagged.id + '_' + j;
							console.log('removing ' + item);
							localforage.removeItem(item);
						}
					}
				}
				if (! target) {
					console.log('removing geotagged_index');
					localforage.removeItem('geotagged_index');
				}
			});
		}
	}

	return self;
})();
