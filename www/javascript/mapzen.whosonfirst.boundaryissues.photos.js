var mapzen = mapzen || {};
mapzen.whosonfirst = mapzen.whosonfirst || {};
mapzen.whosonfirst.boundaryissues = mapzen.whosonfirst.boundaryissues || {};

// for the time being this assumes jQuery is present
// that decision may change some day but not today
// (20160121/thisisaaronland)

mapzen.whosonfirst.boundaryissues.photos = (function() {

	var self = {

		init: function(){
			$('.wof-photo img').click(function(e){
				self.assign_flickr_photo(e.target);
			});
			var wof_id = $('input[name="wof_id"]').val();
			if (wof_id && $('#photos-form').length > 0){
				self.mark_photos(wof_id);
			} else if (wof_id && $('#edit-form #photos').length > 0){
				self.show_photo_thumb(wof_id);
			}
		},

		show_photo_thumb: function(wof_id){
			self.load_photos(wof_id, function(rsp){
				var url = mapzen.whosonfirst.boundaryissues.utils.abs_root_urlify('/id/' + wof_id + '/photos/');
				if (! rsp.photos || rsp.photos.length == 0){
					$('#photos').html('<p><a href="' + url + '">Select a photo</a></p>');
					return;
				}
				var photo = rsp.photos[0];
				var img = '<a href="' + url + '"><img src="' + photo.src + '" alt="Photo"></a>';
				$('#photos').html('<p>' + img + '<a href="' + url + '">Edit photo selection</a></p>');
			});
		},

		mark_photos: function(wof_id){
			self.load_photos(wof_id, function(rsp){
				if (! rsp.photos || rsp.photos.length == 0){
					return;
				}
				var photo = rsp.photos[0];
				if (photo.type == 'flickr'){
					$('#wof-photo-flickr-' + photo.info.id).addClass('wof-photo-primary');
				}
			});
		},

		load_photos: function(wof_id, onsuccess){

			var onerror = function(){
				mapzen.whosonfirst.log.error('Could not load photos.');
			};

			var data = {
				wof_id: wof_id
			};
			mapzen.whosonfirst.boundaryissues.api.api_call("wof.get_photos", data, onsuccess, onerror);
		},

		assign_flickr_photo(el){
			var $figure = $(el).closest('figure');

			var onsuccess = function(){
				$('#photos-form .wof-photo-primary').removeClass('wof-photo-primary');
				$figure.addClass('wof-photo-primary');
				var src = $(el).attr('src');

				if ($('#primary-photo').length == 0){
					$('#primary-photo').html(
						'<h3>Primary photo</h3>' +
						'<img src="' + src + '" id="primary-photo">'
					);
				} else {
					$('#primary-photo').attr('src', src);
				}
			};

			var onerror = function(){
			};

			var wof_id = $('input[name="wof_id"]').val();
			var flickr_id = $figure.data('flickr-id');

			var data = {
				wof_id: wof_id,
				flickr_id: flickr_id
			};

			mapzen.whosonfirst.boundaryissues.api.api_call("wof.assign_flickr_photo", data, onsuccess, onerror);
		}

	};

	return self;

})();

$(document).ready(function(){
	mapzen.whosonfirst.boundaryissues.photos.init();
});
