var mapzen = mapzen || {};
mapzen.whosonfirst = mapzen.whosonfirst || {};
mapzen.whosonfirst.leaflet = mapzen.whosonfirst.leaflet || {};

mapzen.whosonfirst.leaflet.tangram = (function(){

	var _scenefile = '/static/tangram/scene.yaml'
	var _scene_options = {};
	var _sources = {};
	var _cache = {};
	var _prefs_key = null;

	var self = {

		'map_with_bbox': function(id, swlat, swlon, nelat, nelon){

			if ((swlat == nelat) && (swlon == nelon)){
				return self.map_with_latlon(id, swlat, swlon, 14);
			}

			var map = self.map(id);
			map.fitBounds([[swlat, swlon], [nelat, nelon]]);

			return map;
		},

		'map_with_latlon': function(id, lat, lon, zoom){

			var map = self.map(id);
			map.setView([ lat , lon ], zoom);

			return map;
		},

		'map_with_prefs': function(id, prefs_key, cb){

			self.prefs_key(prefs_key);

			self.prefs_load(function(prefs){

				if (prefs && prefs.scenefile){
					self.scenefile(prefs.scenefile);
				}
				if (prefs && prefs.scene_options){
					self.scene_options(prefs.scene_options);
				}
				if (prefs && prefs.sources){
					self.sources(prefs.sources);
				}

				var map = self.map(id);
				cb(map, prefs);

			});
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

		'tangram': function(scenefile, options, sources){

			if (! scenefile){
				scenefile = self.scenefile();
			}

			if (! options){
				options = self.scene_options();
			}

			if (! sources){
				sources = self.sources();
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

			tangram.scene.subscribe({
				load: function(){
					for (var source in sources){
						tangram.scene.setDataSource(source, sources[source]);
					}
					tangram.scene.updateConfig();
				}
			});

			return tangram;
		},

		'scenefile': function(url){

			if (url){
				_scenefile = url;
				self.prefs_store('scenefile', url);
			}

			return _scenefile;
		},

		'scene_options': function(options){

			if (options){
				_scene_options = L.extend(_scene_options, options);
				self.prefs_store('scene_options', options);
			}

			return _scene_options;
		},

		'sources': function(sources){

			if (sources){
				_sources = L.extend(_sources, sources);
				self.prefs_store('sources', sources);
			}

			return _sources;
		},

		'prefs_key': function(prefs_key){

			if (prefs_key){
				_prefs_key = prefs_key;
			}

			return _prefs_key;
		},

		'prefs_load': function(cb){

			var prefs_key = self.prefs_key();

			if (prefs_key){
				localforage.getItem(prefs_key).then(cb);
			}
			else {
				cb();
			}
		},

		'prefs_store': function(key, value){

			var prefs_key = self.prefs_key();

			if (prefs_key){
				self.prefs_load(function(prefs){
					if (! prefs){
						prefs = {};
					}
					prefs[key] = value;
					localforage.setItem(prefs_key, prefs);
				});
			}
		},

		'prefs_reset': function(){
			var prefs_key = self.prefs_key();
			localforage.removeItem(prefs_key);
		}
	};

	return self;
})();
