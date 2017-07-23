var mapzen = mapzen || {};
mapzen.whosonfirst = mapzen.whosonfirst || {};

mapzen.whosonfirst.geotagged = (function() {

	var _reader = new FileReader(),
	    _queue = [],
	    _pending = null,
	    _file = null;

	var self = {

		index: {
			geotagged_ids: [],
			imported_wof_ids: {}
		},

		// See also: self.set_handler(handler, callback)
		handlers: {
			load_index: function(index) {},
			store_photo: function(photo) {},
			store_photos_complete: function(index) {},
			error: function(err) {}
		},

		// These are for your HTML/CSS, so photos don't look sideways
		// or upside-down
		exif_orientation_classes: {
			case1: '',
			case2: 'flip-horiz',
			case3: 'rotate-180',
			case4: 'flip-horiz rotate-180',
			case5: 'flip-horiz rotate-90',
			case6: 'rotate-90',
			case7: 'flip-horiz rotate-270',
			case8: 'rotate-270'
		},

		load_index: function(onsuccess, onerror) {

			if (onsuccess) {
				self.set_handler('load_index', onsuccess);
			}

			if (onerror) {
				self.set_handler('error', onerror);
			}

			localforage.getItem('geotagged').then(function(rsp) {

				// Make sure the stored index smells right
				if (typeof rsp == 'object') {
					self.index = rsp;
				}

				self.handlers.load_index(self.index);

			}).catch(self.handlers.error);
		},

		store_index: function() {
			localforage.setItem('geotagged', self.index);
		},

		set_handler: function(handler, callback) {
			self.handlers[handler] = callback;
		},

		store_photos: function(photos) {

			_queue = photos;
			//_queue.reverse();
			_reader.onerror = self.handlers.error;

			self.store_next_photo();

		},

		store_next_photo: function() {

			if (_queue.length == 0) {
				self.handlers.store_photos_complete(self.index);
			} else {
				_file = _queue.shift();
				_pending = {
					id: null,
					filename: _file.name,
					exif: null,
					geotags: null,
					data_uri: null,
					orientation: null
				};
				_reader.onload = self.handle_array_buffer;
				_reader.readAsArrayBuffer(_file);
			}
		},

		handle_array_buffer: function(e) {

			if (! e.target ||
			    ! e.target.result) {
				self.handlers.error('Oops, something went wrong parsing EXIF tags.');
				return;
			}

			var exif = EXIF.readFromBinaryFile(e.target.result);
			_pending.exif = exif;

			if (exif.GPSLatitude &&
			    exif.GPSLatitudeRef &&
			    exif.GPSLongitude &&
			    exif.GPSLongitudeRef) {
				_pending.geotags = self.parse_geotags(exif);
			}
			if (exif.Orientation) {
				_pending.orientation = self.parse_orientation(exif);
			}

			_reader.onload = self.handle_data_uri;
			_reader.readAsDataURL(_file);
		},

		handle_data_uri: function(e) {

			if (! e.target ||
			    ! e.target.result) {
				self.handlers.error('Oops, something went wrong processing a data URI.');
				return;
			}

			_pending.data_uri = e.target.result;
			_pending.id = 'geotagged_' + (new Date().getTime());

			localforage.setItem(_pending.id, _pending)
				.then(function() {
					self.index.geotagged_ids.push(_pending.id);
					self.store_index();

					// Ok, call the handler with the photo
					self.handlers.store_photo(_pending);

					_pending = null;
					_file = null;

					self.store_next_photo(); // do it again!
				});
		},

		load_photo: function(geotagged_id, callback) {
			localforage.getItem(geotagged_id)
				.then(callback)
				.catch(self.handlers.error);
		},

		remove_photo: function(geotagged_id, callback) {
			localforage.removeItem(geotagged_id)
				.then(callback)
				.catch(self.handlers.error);

			var index = self.index.geotagged_ids.indexOf(geotagged_id);
			if (index == -1) {
				return;
			}
			self.index.geotagged_ids.splice(index, 1);
			delete self.index.imported_wof_ids[geotagged_id];
			self.store_index();
		},

		reset_localforage: function() {
			localforage.getItem('geotagged').then(function(index) {
				if (! index ||
				    ! index.geotagged_ids) {
					return;
				}
				for (var i = 0; i < index.geotagged_ids.length; i++) {
					localforage.removeItem(index.geotagged_ids[i]);
				}
				localforage.removeItem('geotagged');
			});
		},

		// Adapted from https://stackoverflow.com/a/2572991/937170
		parse_geotags: function(exif) {
			var lat = self.parse_exif_geotag(exif.GPSLatitude, exif.GPSLatitudeRef);
			var lng = self.parse_exif_geotag(exif.GPSLongitude, exif.GPSLongitudeRef);
			var geotags = {
				latitude: lat,
				longitude: lng
			};
			if (exif.GPSAltitude && exif.GPSAltitudeRef) {

				// altitude is in meters
				geotags.altitude = parseFloat(exif.GPSAltitude);

				// if ref is 0: altitude is above sea level
				// if ref is 1: altitude is below sea level
				if (exif.GPSAltitudeRef) {
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

		parse_orientation: function(exif) {
			switch (exif.Orientation) {
				case 1:
					return self.exif_orientation_classes.case1;
				case 2:
					return self.exif_orientation_classes.case2;
				case 3:
					return self.exif_orientation_classes.case3;
				case 4:
					return self.exif_orientation_classes.case4;
				case 5:
					return self.exif_orientation_classes.case5;
				case 6:
					return self.exif_orientation_classes.case6;
				case 7:
					return self.exif_orientation_classes.case7;
				case 8:
					return self.exif_orientation_classes.case8;
			}
		}
	}

	return self;
})();
