var mapzen = mapzen || {};
mapzen.whosonfirst = mapzen.whosonfirst || {};
mapzen.whosonfirst.boundaryissues = mapzen.whosonfirst.boundaryissues || {};

mapzen.whosonfirst.boundaryissues.events = (function() {

	var self = {
		init: function() {
			$('.event-log .event').each(function(i, item) {
				if ($(item).data('created')) {
					var created = parseInt($(item).data('created')) * 1000;
					var timestamp = moment(created).fromNow();
					$(item).append(' <small>' + timestamp + '</small>');
				}
				$(item).find('a').each(function(j, link) {
					var url = $(link).attr('href');
					$(link).attr('href', mapzen.whosonfirst.boundaryissues.utils.abs_root_urlify(url));
				});
			});
		}
	};

	$(document.body).ready(function() {
		self.init();
	});

	return self;
})();
