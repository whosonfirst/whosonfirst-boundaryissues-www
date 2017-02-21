var mapzen = mapzen || {};
mapzen.whosonfirst = mapzen.whosonfirst || {};
mapzen.whosonfirst.boundaryissues = mapzen.whosonfirst.boundaryissues || {};

// for the time being this assumes jQuery is present
// that decision may change some day but not today
// (20160121/thisisaaronland)

mapzen.whosonfirst.boundaryissues.venue = (function() {
	
	var self = {
		
	};
	
	function setup_map() {
		var scene = mapzen.whosonfirst.boundaryissues.utils.abs_root_urlify('/tangram/refill.yaml');
		mapzen.whosonfirst.leaflet.tangram.scenefile(scene);
		
		// "Oh hey, wtf this is just hardcoded with NYC?"
		// Yeah I know, I'll fix that.
		// (20170209/dphiffer)
		
		var lat = 40.73581157695217;
		var lon = -73.9815902709961;
		var zoom = 12;
		map = mapzen.whosonfirst.leaflet.tangram.map_with_latlon(
			'map',
			lat, lon, zoom
		);
	}
	
	$(document).ready(function() {
		if ($('#venue').length > 0) {
			setup_map();
		}
	});
	
	return self;

})();
