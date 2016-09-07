var mapzen = mapzen || {};
mapzen.whosonfirst = mapzen.whosonfirst || {};

mapzen.whosonfirst.nearby = (function(){

	var venues_layer = undefined;
	var venues_features = [];
	var venues_geojson = undefined;
	
	var mask_layer = undefined;
	var nearby_points = [];
	
	var _nearby = undefined;
	var _map = undefined;
	
	var self = {
		
		'init': function(map){
			
			_map = map;
			
			_map.on('load', self.inflate_nearby);
			_map.on('dragend', self.inflate_nearby);
			_map.on('zoomend', self.inflate_nearby);

			_nearby = document.getElementById("whosonfirst-nearby");			
		},
		
		'inflate_nearby': function(){
			
			// I guess this is what promises are for... (20160815/thisisaaronland)
			
			nearby_points = [];
			
			mapzen.nearby.inflate_nearby_venues(function(){
				mapzen.nearby.draw_nearby_features();
			});
		},
		
		'inflate_nearby_venues': function(cb){
			
			autocomplete_venues = [];
			
			venues_features = [];
			venues_drawn = {};
			
			var center = _map.getCenter();
			
			var method = 'whosonfirst.places.getNearby';
			
			var args = {
				'latitude': center['lat'], 'longitude': center['lng'],
			};
			
			var on_success = function(rsp){

				if (_nearby){
					_nearby.innerHTML = '';
				}
				
				var results = rsp['results'];
				var count = results.length;
				
				for (var i=0; i < count; i++){
					
					var row = results[i];
					
					var geom = { 'type': 'Point', 'coordinates': [ row['geom:longitude'], row['geom:latitude'] ] };
					var props = { 'id': row['wof:id'], 'name': row['wof:name'], 'lflt:label_text': row['wof:name'] };
					var feature = {'type': 'Feature', 'geometry': geom, 'properties': props };
					
					venues_features.push(feature);
					
					nearby_points.push([row['geom:latitude'], row['geom:longitude']]);

					if (_nearby){
						
						var el = document.createElement("li");
						el.setAttribute("id", row['wof:id']);					
						el.setAttribute("data-latitude", row['geom:latitude']);
						el.setAttribute("data-longitude", row['geom:longitude']);		    
						el.appendChild(document.createTextNode(row['wof:name']));
						el.onclick = self.jump_to_target;
						
						nearby.appendChild(el);
					}
				}
				
				venues_geojson = { 'type': 'FeatureCollection', 'features': venues_features };
				
				// figure out if we need to keep going...
				
				if (rsp['cursor']){
					
					args['cursor'] = rsp['cursor'];
					mapzen.whosonfirst.boundaryissues.api.call(method, args, on_success);
					return;
				}
				
				// see above inre: promises...
				
				if (cb){
					cb();
				}
			}
			
			mapzen.whosonfirst.boundaryissues.api.call(method, args, on_success);
		},
		
		'draw_nearby_features': function(){
			
			self.draw_nearby_mask();
			
			if (venues_layer){
				_map.removeLayer(venues_layer);
			}
			
			var style = mapzen.whosonfirst.leaflet.styles.search_centroid();
			var handler = mapzen.whosonfirst.leaflet.handlers.point(style);

			venues_layer = L.geoJson(venues_geojson, {
				'style': style,
				'pointToLayer': handler,
				'onEachFeature': function(feature, layer){
					layer.on('click', function(){
						console.log(feature.properties['wof:id']);
						var path = '/id/' + feature.properties['wof:id'];
						var url = mapzen.whosonfirst.boundaryissues.utils.abs_root_urlify(path);
						location.href = url;
					});
				}
			});
			
			venues_layer.addTo(map);
		},

		'draw_nearby_mask': function(){
			
			if (mask_layer){
				_map.removeLayer(mask_layer);
			}
			
			var bounds = _map.getBounds();
			
			var sw = bounds.getSouthWest();
			var nw = bounds.getNorthWest();
			var se = bounds.getSouthEast();
			var ne = bounds.getNorthEast();
			
			var outer = [
				[ sw['lng'], sw['lat'] ],
				[ nw['lng'], nw['lat'] ],
				[ ne['lng'], ne['lat'] ],
				[ se['lng'], se['lat'] ],
				[ sw['lng'], sw['lat'] ]
			];
			
			var count_points = nearby_points.length;
			
			var swlat = undefined;
			var swlon = undefined;
			var nelat = undefined;
			var nelon = undefined;
			
			for (var i=0; i < count_points; i++){
				
				var lat = nearby_points[i][0];
				var lon = nearby_points[i][1];
				
				if ((typeof(swlat) == "undefined") || (lat < swlat)){
					swlat = lat;
				}
				
				if ((typeof(nelat) == "undefined") || (lat > nelat)){
					nelat = lat;
				}
				
				if ((typeof(swlon) == "undefined") || (lon < swlon)){
					swlon = lon;
				}
				
				if ((typeof(nelon) == "undefined") || (lon > nelon)){
					nelon = lon;
				}
				
				// console.log("lat " + lat + " sw " + swlat + " ne " + nelat);
			}
			
			var inner = [
				[ swlon, swlat ],
				[ swlon, nelat ],
				[ nelon, nelat ],
				[ nelon, swlat ],
				[ swlon, swlat ],
			];
			
			var coords = [[
				outer,
				inner,
			]];
			
			var geom = {'type': 'MultiPolygon', 'coordinates': coords };
			var feature = {'type': 'Feature', 'geometry': geom };
			
			var style = mapzen.whosonfirst.leaflet.styles.bbox();
			
			mask_layer = L.geoJson(feature, {
				'style': style,
			});
			
			mask_layer.addTo(_map);
		},

		'jump_to_target': function(event){
			
			var el = event.target;
			var id = el.getAttribute("data-id");

			alert("go to " + id);

			/*
			  var lat = el.getAttribute("data-latitude");
			  var lon = el.getAttribute("data-longitude");
			  var map = maplibs.slippymap.map();			
			  map.setView([lat, lon], 18);
			*/
		},
	};

	return self;

})();
