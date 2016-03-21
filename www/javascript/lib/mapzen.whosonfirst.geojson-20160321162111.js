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

					var _swlat, _swlon, _nelat, _nelon = bbox;
					
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
				
				var geom = geojson['geometry'];
				
				// 
			}
			
			else {}
		}

	};

	return self;

})();
