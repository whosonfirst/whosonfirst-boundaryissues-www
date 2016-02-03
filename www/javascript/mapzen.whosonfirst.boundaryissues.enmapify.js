var mapzen = mapzen || {};
mapzen.whosonfirst = mapzen.whosonfirst || {};
mapzen.whosonfirst.boundaryissues = mapzen.whosonfirst.boundaryissues || {};

mapzen.whosonfirst.boundaryissues.enmapify = (function(){

	var self = {
		'render_id': function(map, wofid, on_fetch){

			var _self = self;

			if (! wofid){
				mapzen.whosonfirst.log.error("failed to enmapify because missing WOF ID");
				return false;
			}

			if (! on_fetch){

				on_fetch = function(geojson){
					self.render_feature(map, geojson);
				};
			}

			var url = mapzen.whosonfirst.data.id2abspath(wofid);

			mapzen.whosonfirst.net.fetch(url, on_fetch);
		},

		'render_feature_outline': function(map, feature){

			mapzen.whosonfirst.leaflet.fit_map(map, feature);
			mapzen.whosonfirst.leaflet.draw_poly(map, feature, mapzen.whosonfirst.leaflet.styles.parent_polygon());
		},

		'render_feature': function(map, feature){

			mapzen.whosonfirst.leaflet.fit_map(map, feature);

			var props = feature['properties'];

			var parent_id = props['wof:parent_id'];
			var parent_url = mapzen.whosonfirst.data.id2abspath(parent_id);

			var on_parent = function(parent_feature){

				mapzen.whosonfirst.leaflet.fit_map(map, parent_feature);

				parent_feature['properties']['lflt:label_text'] = parent_feature['properties']['wof:name'];
				mapzen.whosonfirst.leaflet.draw_poly(map, parent_feature, mapzen.whosonfirst.leaflet.styles.parent_polygon());

				var on_fail = function(){
					mapzen.whosonfirst.log.error("failed to render " + parent_url);
					on_child(feature);
				};

				on_child(feature);
			};

			var on_child = function(child_feature){

				var props = child_feature['properties'];
				var geom = child_feature['geometry'];

				var lat = props['geom:latitude'];
				var lon = props['geom:longitude'];

				var label_text = 'math centroid (shapely) is ';
				label_text += lat + ", " + lon;

				var pt = {
					'type': 'Feature',
					'geometry': { 'type': 'Point', 'coordinates': [ lon, lat ] },
					'properties': { 'lflt:label_text': label_text }
				};

				if (geom['type'] == 'Point'){

					var name = props['wof:name'];

					var label_text = name;
					label_text += ', whose centroid is ';
					label_text += lat + ", " + lon;

					pt['properties']['lflt:label_text'] = label_text;

					var style = mapzen.whosonfirst.leaflet.styles.geom_centroid();
					var handler = mapzen.whosonfirst.leaflet.handlers.point(style);

					mapzen.whosonfirst.leaflet.draw_point(map, pt, style, handler);
					return;
				}

				var force = true;
				mapzen.whosonfirst.leaflet.fit_map(map, child_feature, force);

				child_feature['properties']['lflt:label_text'] = "";
				mapzen.whosonfirst.leaflet.draw_bbox(map, child_feature, mapzen.whosonfirst.leaflet.styles.bbox());

				child_feature['properties']['lflt:label_text'] = child_feature['properties']['wof:name'];
				mapzen.whosonfirst.leaflet.draw_poly(map, child_feature, mapzen.whosonfirst.leaflet.styles.consensus_polygon());

				// we're defining this as a local function to ensure that it gets called
				// after any breaches are drawn (20150909/thisisaaronland)

				var draw_centroids = function(){

					// I don't know why this is necessary...
					// (20150909/thisisaaronland)

					if (! pt){
						var lat = props['geom:latitude'];
						var lon = props['geom:longitude'];

						var label_text = 'math centroid (shapely) is ';
						label_text += lat + ", " + lon;

						var pt = {
							'type': 'Feature',
							'geometry': { 'type': 'Point', 'coordinates': [ lon, lat ] },
							'properties': { 'lflt:label_text': label_text }
						};
					}

					var style = mapzen.whosonfirst.leaflet.styles.math_centroid();
					var handler = mapzen.whosonfirst.leaflet.handlers.point(style);

					mapzen.whosonfirst.leaflet.draw_point(map, pt, style, handler);

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

						mapzen.whosonfirst.leaflet.draw_point(map, pt, style, handler);
					}

				};

				var breaches = props['wof:breaches']
				var count = breaches.length;

				if (! count){
					draw_centroids();
				}

				else {

					for (var i=0; i < count; i++){

						var breach_id = breaches[i];
						var breach_url = mapzen.whosonfirst.data.id2abspath(breach_id);

						var breach_style = mapzen.whosonfirst.leaflet.styles.breach_polygon();

						var on_fetch = function(breach_feature){

							var child_props = child_feature['properties'];
							var child_name = child_props['wof:name'];

							var breach_props = breach_feature['properties'];
							var breach_name = breach_props['wof:name'];

							var label_text = breach_name + " breaches " + child_name;

							props['lflt:label_text'] = label_text;
							breach_feature['properties'] = props;

							mapzen.whosonfirst.leaflet.draw_poly(map, breach_feature, breach_style);

							if (i == count){
								draw_centroids();
							}

						};

						mapzen.whosonfirst.net.fetch(breach_url, on_fetch);
					}
				}

			}

			if ((! parent_id) || (parent_id == -1)){
				mapzen.whosonfirst.net.fetch(child_url, on_child);
			}

			else {
				mapzen.whosonfirst.net.fetch(parent_url, on_parent, function(){ on_child(feature) });
			}
		},

		'enmapify_feature': function(map, collection){
			// please write me
		}
	};

	return self;

})();
