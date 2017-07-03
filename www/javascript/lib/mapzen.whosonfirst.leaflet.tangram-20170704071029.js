var mapzen = mapzen || {};
mapzen.whosonfirst = mapzen.whosonfirst || {};
mapzen.whosonfirst.leaflet = mapzen.whosonfirst.leaflet || {};

mapzen.whosonfirst.leaflet.tangram = (function(){

	var _scenefile = '/static/tangram/scene.yaml'
	var _scene_options = {};
	var _cache = {};

	var self = {

		'map_with_bbox': function(id, swlat, swlon, nelat, nelon){

			if ((swlat == nelat) && (swlon == nelon)){
				return self.map_with_latlon(id, swlat, swlon, 14);
			}

			var map = self.map(id);
			map.fitBounds([[swlat, swlon], [ nelat, nelon ]]);

			return map;
		},

		'map_with_latlon': function(id, lat, lon, zoom){

			var map = self.map(id);
			map.setView([ lat , lon ], zoom);

			return map;
		},

		'map': function(id){

			if (! _cache[id]){
				var map = L.map(id);
				map.scrollWheelZoom.disable();

				var tangram = self.tangram(self.scenefile(), self.scene_options());
				tangram.addTo(map);

				_cache[id] = map;
			}

			return _cache[id];
		},

		'tangram': function(scenefile, options){

			if (! scenefile) {
				scenefile = self.scenefile();
			}

			if (! options) {
				options = self.scene_options();
			}

			var scene = {
				import: scenefile,
				global: options
			};

			var tangram = Tangram.leafletLayer({
				scene: scene,
				numWorkers: 2,
				unloadInvisibleTiles: false,
				updateWhenIdle: false
			});

			return tangram;
		},

		'scenefile': function(url){

			if (url){
				_scenefile = url;
			}

			return _scenefile;
		},

		'scene_options': function(options){

			if (options){
				_scene_options = L.extend(_scene_options, options);
			}

			return _scene_options;
		}
	};

	return self;
})();
