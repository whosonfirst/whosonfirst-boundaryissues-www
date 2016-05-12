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
	    geojson_file,
	    upload_is_ready = false;

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
				if (! upload_is_ready) {
					return;
				}
				var crumb = $(this).data("crumb-upload");
				self.post_file(crumb);
			});
		},

		show_preview: function(){

			// Read the file and display a preview map prior to uploading
			var reader = new FileReader();
			reader.onload = function(e){
				try {
					var geojson = JSON.parse(reader.result);
				} catch(e) {
					$result.html(e);
					upload_is_ready = false;
					return;
				}

				upload_is_ready = true;
				$('#upload-btn').addClass('btn-primary');
				$('#upload-btn').attr('disabled', false);

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

				// Clear the map if a feature is already on there
				map.eachLayer(function(layer) {
					if (layer.feature) {
						map.removeLayer(layer);
					}
				});

				if (geojson.type == "Feature") {
					mapzen.whosonfirst.boundaryissues.enmapify.render_feature(map, geojson);
				} else if (geojson.type == "FeatureCollection") {
					self.show_collection_preview(map, geojson);
				}
			}

			// Load up the file to kick off the preview
			if (geojson_file){
				reader.readAsText(geojson_file);
				$result.html('This is just a preview. You still have to hit the upload button.');
			} else {
				mapzen.whosonfirst.log.error('No geojson_file to preview.');
			}
		},

		show_collection_preview: function(map, collection) {
			mapzen.whosonfirst.leaflet.fit_map(map, collection);
			var feature;
			for (var i = 0; i < collection.features.length; i++) {
				feature = collection.features[i];
				mapzen.whosonfirst.leaflet.draw_poly(map, feature, mapzen.whosonfirst.leaflet.styles.consensus_polygon());
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
			if ($('input[name="ignore_properties"]').get(0).checked) {
				data.append('ignore_properties', 1);
			}
			mapzen.whosonfirst.boundaryissues.api.api_call("wof.upload", data, onsuccess, onerror);

			// Show some user feedback
			$result.html('Uploading...');
		},

		show_result: function(rsp){
			if (rsp.ok && rsp.stat == 'ok') {
				var what_happened = rsp.is_update ? 'Updated ' : 'Created ';
				var geojson_link = '<a href="' + rsp.geojson_url + '">' + rsp.id + '.geojson</a>';
				$result.html('Success! ' + what_happened + geojson_link);
				mapzen.whosonfirst.log.debug(rsp);
			} else if (rsp.error_msg) {
				$result.html('Error: ' + rsp.error_msg);
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
