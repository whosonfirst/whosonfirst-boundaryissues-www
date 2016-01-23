var mapzen = mapzen || {};
mapzen.whosonfirst = mapzen.whosonfirst || {};
mapzen.whosonfirst.boundaryissues = mapzen.whosonfirst.boundaryissues || {};

// for the time being this assumes jQuery is present
// that decision may change some day but not today
// (20160121/thisisaaronland)

mapzen.whosonfirst.boundaryissues.ui = (function(){

	var self = {

		setup_upload: function(){
			var upload_file;
			var f = $(".upload-form");
			f.submit(function(e){

				e.preventDefault();

				var onsuccess = function(rsp){ console.log(rsp); };
				var onerror = function(rsp){ console.log(rsp); };

				var crumb = f.data("crumb-upload");
				var data = new FormData();
				data.append('crumb', crumb);
				data.append('upload_file', upload_file);
				mapzen.whosonfirst.boundaryissues.api.api_call("wof.upload", data, onsuccess, onerror);
			});

			$(".upload-form input[name=upload_file]").on('change', function(e) {
				upload_file = e.target.files[0];
				var reader = new FileReader();
				reader.onload = function(e) {
					var geojson = JSON.parse(reader.result);
					$('#map').removeClass('hidden');
					mapzen.whosonfirst.leaflet.tangram.scenefile('/tangram/refill.yaml');

					var swlat = 37.70120736474139;
					var swlon = -122.68707275390624;
					var nelat = 37.80924146650164;
					var nelon = -122.21912384033203;

					map = mapzen.whosonfirst.leaflet.tangram.map_with_bbox('map', swlat, swlon, nelat, nelon);
					mapzen.whosonfirst.boundaryissues.enmapify.render_feature(map, geojson);
				}
				reader.readAsText(upload_file);
			});
		},

		show_preview: function(geojson){
			console.log(geojson);
		}
	};

	$(document).ready(function(){
		self.setup_upload();
	});

	return self;
})();
