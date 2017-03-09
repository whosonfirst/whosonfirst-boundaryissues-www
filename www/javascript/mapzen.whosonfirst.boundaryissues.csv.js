var mapzen = mapzen || {};
mapzen.whosonfirst = mapzen.whosonfirst || {};
mapzen.whosonfirst.boundaryissues = mapzen.whosonfirst.boundaryissues || {};

// for the time being this assumes jQuery is present
// that decision may change some day but not today
// (20160121/thisisaaronland)

mapzen.whosonfirst.boundaryissues.csv = (function(){

	var self = {

	};

	$(document).ready(function() {
		if ($('#download-csv').length == 0) {
			return;
		}
		$('#download-csv').click(function(e) {
			e.preventDefault();
			
		});
	});

	return self;

})();
