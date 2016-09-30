var mapzen = mapzen || {};
mapzen.whosonfirst = mapzen.whosonfirst || {};

mapzen.whosonfirst.leaflet = (function(){

	var self = {
		'draw_point': function(map, geojson, style, handler){

			var layer = L.geoJson(geojson, {
				'style': style,
				'pointToLayer': handler,
			});

			return layer.addTo(map);
		},

		'draw_poly': function(map, geojson, style){

			var layer = L.geoJson(geojson, {
				'style': style
			});

			// https://github.com/Leaflet/Leaflet.label

			try {
				var props = geojson['properties'];
				var label = props['lflt:label_text'];

				if (label){
					layer.bindLabel(label, {noHide: true });
				}
			}

			catch (e){
				console.log("failed to bind label because " + e);
			}

			return layer.addTo(map);
		},

		'draw_bbox': function(map, geojson, style){

			var bbox = mapzen.whosonfirst.geojson.derive_bbox(geojson);

			if (! bbox){
				console.log("no bounding box");
				return false;
			}

			var bbox = geojson['bbox'];
			var swlat = bbox[1];
			var swlon = bbox[0];
			var nelat = bbox[3];
			var nelon = bbox[2];

			var geom = {
				'type': 'Polygon',
				'coordinates': [[
					[swlon, swlat],
					[swlon, nelat],
					[nelon, nelat],
					[nelon, swlat],
					[swlon, swlat],
				]]
			};

			var bbox_geojson = {
				'type': 'Feature',
				'geometry': geom
			}

			return self.draw_poly(map, bbox_geojson, style);
		},

		'draw_centroids': function(map, geojson){

			var props = geojson.properties;
			var lat = props['geom:latitude'];
			var lon = props['geom:longitude'];

			var label_text = 'math centroid (shapely) is ';
			label_text += lat + ", " + lon;

			var pt = {
				'type': 'Feature',
				'geometry': { 'type': 'Point', 'coordinates': [ lon, lat ] },
				'properties': { 'lflt:label_text': label_text }
			};

			var style = mapzen.whosonfirst.leaflet.styles.math_centroid();
			var handler = mapzen.whosonfirst.leaflet.handlers.point(style);

			var math_centroid = mapzen.whosonfirst.leaflet.draw_point(map, pt, style, handler);

			if ((props['lbl:latitude']) && (props['lbl:longitude'])){

				var lat = props['lbl:latitude'];
				var lon = props['lbl:longitude'];

				var label_src = props['src:lbl:centroid'] || props['src:centroid_lbl'] || "UNKNOWN";

				var label_text = "label centroid (";
				label_text += label_src;
				label_text += ") is ";
				label_text += lat + ", " + lon;

				var pt = {
					'type': 'Feature',
					'geometry': { 'type': 'Point', 'coordinates': [ lon, lat ] },
					'properties': { 'lflt:label_text': label_text },
				};

				var style = mapzen.whosonfirst.leaflet.styles.label_centroid();
				var handler = mapzen.whosonfirst.leaflet.handlers.point(style);

				var label_centroid = mapzen.whosonfirst.leaflet.draw_point(map, pt, style, handler);
			}
			return {
				math_centroid: math_centroid,
				label_centroid: label_centroid
			};
		},

		'fit_map': function(map, geojson, force){

			var bbox = mapzen.whosonfirst.geojson.derive_bbox(geojson);

			if (! bbox){
				console.log("no bounding box");
				return false;
			}

			if ((bbox[1] == bbox[3]) && (bbox[2] == bbox[4])){
				map.setView([bbox[1], bbox[0]], 14);
				return;
			}

			var sw = [bbox[1], bbox[0]];
			var ne = [bbox[3], bbox[2]];

			var bounds = new L.LatLngBounds([sw, ne]);
			var current = map.getBounds();

			var redraw = true;

			if (! force){

				var redraw = false;

				/*
				  console.log("south bbox: " + bounds.getSouth() + " current: " + current.getSouth().toFixed(6));
				  console.log("west bbox: " + bounds.getWest() + " current: " + current.getWest().toFixed(6));
				  console.log("north bbox: " + bounds.getNorth() + " current: " + current.getNorth().toFixed(6));
				  console.log("east bbox: " + bounds.getEast() + " current: " + current.getEast().toFixed(6));
				*/

				if (bounds.getSouth() <= current.getSouth().toFixed(6)){
					redraw = true;
				}

				else if (bounds.getWest() <= current.getWest().toFixed(6)){
					redraw = true;
				}

				else if (bounds.getNorth() >= current.getNorth().toFixed(6)){
					redraw = true;
				}

				else if (bounds.getEast() >= current.getEast().toFixed(6)){
					redraw = true;
				}

				else {}
			}

			if (redraw){
				map.fitBounds(bounds);
			}
		}
	};

	return self;
})();
