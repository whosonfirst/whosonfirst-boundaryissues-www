var mapzen = mapzen || {};
mapzen.whosonfirst = mapzen.whosonfirst || {};
mapzen.whosonfirst.boundaryissues = mapzen.whosonfirst.boundaryissues || {};

// for the time being this assumes jQuery is present
// that decision may change some day but not today
// (20160121/thisisaaronland)

mapzen.whosonfirst.boundaryissues.ui = (function(){

	// Public UI methods will go here
	var self = {};

	function setup_upload(){
		var files;
		var f = $(".upload-form");
		f.submit(function(e){

			e.preventDefault();

			var onsuccess = function(rsp){ console.log(rsp); };
			var onerror = function(rsp){ console.log(rsp); };

			var crumb = f.data("crumb-upload");
			var data = new FormData();
			data.append('crumb', crumb);
			$.each(files, function(key, value){
				data.append('upload_file', value);
			});
			mapzen.whosonfirst.boundaryissues.api.api_call("wof.upload", data, onsuccess, onerror);
		});

		$(".upload-form input[type=file]").on('change', function(e) {
			files = e.target.files;
		});
	}

	$(document).ready(function(){
		setup_upload();
	});

	return self;
})();
