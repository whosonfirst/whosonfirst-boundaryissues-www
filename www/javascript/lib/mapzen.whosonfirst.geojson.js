var mapzen = mapzen || {};
mapzen.whosonfirst = mapzen.whosonfirst || {};

mapzen.whosonfirst.geojson = (function(){

	var self = {

		'derive_bbox': function(geojson){

			if (geojson['bbox']){
				return geojson['bbox'];
			}

			if (geojson['type'] == 'FeatureCollection'){

				var features = geojson['features'];
				var count = features.length;

				var swlat = undefined;
				var swlon = undefined;
				var nelat = undefined;
				var nelon = undefined;

				for (var i=0; i < count; i++){

					var bbox = self.derive_bbox(features[i]);

					var _swlat = bbox[0];
					var _swlon = bbox[1];
					var _nelat = bbox[2];
					var _nelon = bbox[3];

					if ((! swlat) || (_swlat < swlat)){
						swlat = _swlat;
					}

					if ((! swlon) || (_swlon < swlon)){
						swlon = _swlon;
					}

					if ((! nelat) || (_nelat > nelat)){
						nelat = _nelat;
					}

					if ((! nelon) || (_nelon > nelon)){
						nelon = _nelon;
					}
				}

				return [ swlat, swlon, nelat, nelon ];
			}

			else if (geojson['type'] == 'Feature'){

				// Adapted from http://gis.stackexchange.com/a/172561
				// See also: https://tools.ietf.org/html/rfc7946#section-3.1

				var geom = geojson['geometry'];
				var coords = geom.coordinates;

				var lats = [],
				    lngs = [];

				if (geom.type == 'Point') {
					return [ coords[0], coords[1],
					         coords[0], coords[1] ];
				} else if (geom.type == 'MultiPoint' ||
				           geom.type == 'LineString') {
					for (var i = 0; i < coords.length; i++) {
						lats.push(coords[i][1]);
						lngs.push(coords[i][0]);
					}
				} else if (geom.type == 'MultiLineString') {
					for (var i = 0; i < coords.length; i++) {
						for (var j = 0; j < coords[i].length; j++) {
							lats.push(coords[i][j][1]);
							lngs.push(coords[i][j][0]);
						}
					}
				} else if (geom.type == 'Polygon') {
					for (var i = 0; i < coords[0].length; i++) {
						lats.push(coords[0][i][1]);
						lngs.push(coords[0][i][0]);
					}
				} else if (geom.type == 'MultiPolygon') {
					for (var i = 0; i < coords.length; i++) {
						for (var j = 0; j < coords[i][0].length; j++) {
							lats.push(coords[i][0][j][1]);
							lngs.push(coords[i][0][j][0]);
						}
					}
				}

				var minlat = Math.min.apply(null, lats),
				    maxlat = Math.max.apply(null, lats);
				var minlng = Math.min.apply(null, lngs),
				    maxlng = Math.max.apply(null, lngs);

				return [ minlng, minlat,
					 maxlng, maxlat ];
			}

			else {}
		}

	};

	return self;

})();
