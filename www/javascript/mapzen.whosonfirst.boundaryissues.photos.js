var mapzen = mapzen || {};
mapzen.whosonfirst = mapzen.whosonfirst || {};
mapzen.whosonfirst.boundaryissues = mapzen.whosonfirst.boundaryissues || {};

// for the time being this assumes jQuery is present
// that decision may change some day but not today
// (20160121/thisisaaronland)

mapzen.whosonfirst.boundaryissues.photos = (function() {

	var self = {

		init: function(){
			var wof_id = $('input[name="wof_id"]').val();
			if (wof_id && $('#edit-form #photos').length > 0){
				self.show_photo_thumb(wof_id);
			}
		},

		show_photo_thumb: function(wof_id){
			self.load_photos(wof_id, function(rsp){
				var server = rsp['url'];
				var url = server + '/id/' + wof_id + '/';
				if (! rsp.photos || rsp.photos.length == 0){
					$('#photos').html('<p><a href="' + url + '">Select a photo</a></p>');
					return;
				}
				var photo = rsp.photos[0];
				var img = '<a href="' + url + '"><img src="' + photo.src + '" alt="Photo"></a>';
				$('#photos').html('<p>' + img + '<a href="' + url + '">Edit photo selection</a></p>');
			});
		},

		load_photos: function(wof_id, onsuccess){

			var onerror = function(){
				mapzen.whosonfirst.log.error('Could not load photos.');
			};

			var data = {
				wof_id: wof_id
			};
			mapzen.whosonfirst.boundaryissues.api.api_call("wof.photos_get", data, onsuccess, onerror);
		}

	};

	return self;

})();

$(document).ready(function(){
	mapzen.whosonfirst.boundaryissues.photos.init();
});
