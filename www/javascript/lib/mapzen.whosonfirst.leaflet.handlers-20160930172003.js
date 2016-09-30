var mapzen = mapzen || {};
mapzen.whosonfirst = mapzen.whosonfirst || {};
mapzen.whosonfirst.leaflet = mapzen.whosonfirst.leaflet || {};

mapzen.whosonfirst.leaflet.handlers = (function(){

	var self = {

		'point': function(style){

			return function(feature, latlon){

				var m = L.circleMarker(latlon, style);
				
				// https://github.com/Leaflet/Leaflet.label
				
				try {
					var props = feature['properties'];
					var label = props['lflt:label_text'];
					
					if (label){
						m.bindLabel(label, { noHide: false });
					}
				}
				
				catch (e){
					console.log("failed to bind label because " + e);
				}
				
				return m;
			};
		},
	};
	
	return self;
})();
