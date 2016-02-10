var mapzen = mapzen || {};
mapzen.whosonfirst = mapzen.whosonfirst || {};
mapzen.whosonfirst.boundaryissues = mapzen.whosonfirst.boundaryissues || {};

// for the time being this assumes jQuery is present
// that decision may change some day but not today
// (20160121/thisisaaronland)

mapzen.whosonfirst.boundaryissues.upload = (function(){

	var $form,
	    $result,
	    $preview_map,
	    geojson_file;

	var self = {

		setup_upload: function(){

			// Get some jQuery references to our top-level elements
			$form = $('#upload-form');
			$result = $('#upload-result');
			$preview_map = $('#upload-preview-map');

			// Grab a reference to the file input's data when its onchange fires
			$form.find('input[name=geojson_file]').on('change', function(e){
				geojson_file = e.target.files[0];
				self.show_preview(geojson_file);
			});

			// Intercept the form submit event and upload the file via API
			$form.submit(function(e){
				e.preventDefault();
				var crumb = $(this).data("crumb-upload");
				self.post_file(crumb);
			});
		},

		show_preview: function(){

			// Read the file and display a preview map prior to uploading
			var reader = new FileReader();
			reader.onload = function(e){
				var geojson = JSON.parse(reader.result);

				$preview_map.removeClass('hidden');
				mapzen.whosonfirst.leaflet.tangram.scenefile('/tangram/refill.yaml');

				var swlat = 37.70120736474139;
				var swlon = -122.68707275390624;
				var nelat = 37.80924146650164;
				var nelon = -122.21912384033203;

				// Show the preview map
				var map = mapzen.whosonfirst.leaflet.tangram.map_with_bbox(
					'upload-preview-map',
					swlat, swlon, nelat, nelon
				);
				mapzen.whosonfirst.boundaryissues.enmapify.render_feature(map, geojson);
			}

			// Load up the file to kick off the preview
			if (geojson_file){
				reader.readAsText(geojson_file);
			} else {
				mapzen.whosonfirst.log.error('No geojson_file to preview.');
			}
		},

		post_file: function(crumb){

			var onsuccess = function(rsp){
				self.show_result(rsp);
				mapzen.whosonfirst.log.debug(rsp);
			};
			var onerror = function(rsp){
				self.show_result(rsp);
				mapzen.whosonfirst.log.error(rsp);
			};

			// Make sure we have a geojson_file reference set up
			if (! geojson_file){
				mapzen.whosonfirst.log.error('No geojson_file to post.');
				return;
			}

			// Assemble our form data and send it along to the API method
			var data = new FormData();
			data.append('crumb', crumb);
			data.append('upload_file', geojson_file);
			mapzen.whosonfirst.boundaryissues.api.api_call("wof.upload", data, onsuccess, onerror);

			// Show some user feedback
			$result.html('Uploading...');
		},

		show_result: function(rsp){
			if (rsp.result && rsp.result.ok) {
				var what_happened = rsp.result.result == 'update' ? 'Updated ' : 'Created ';
				var geojson_link = '<a href="' + rsp.result.geojson_url + '">' + rsp.result.id + '.geojson</a>';
				$result.html('Success! ' + what_happened + geojson_link);
				mapzen.whosonfirst.log.debug(rsp);
			} else if (rsp.result.error_msg) {
				$result.html('Error: ' + rsp.result.error_msg);
				mapzen.whosonfirst.log.error(rsp);
			} else {
				$result.html('Oh noes, an error! Check the JavaScript console?');
				mapzen.whosonfirst.log.error(rsp);
			}
		}

	};

	$(document).ready(function(){
		self.setup_upload();
	});

	return self;
})();
