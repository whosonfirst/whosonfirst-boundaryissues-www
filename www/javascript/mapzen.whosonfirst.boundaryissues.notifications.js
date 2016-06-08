var mapzen = mapzen || {};
mapzen.whosonfirst = mapzen.whosonfirst || {};
mapzen.whosonfirst.boundaryissues = mapzen.whosonfirst.boundaryissues || {};

// for the time being this assumes jQuery is present
// that decision may change some day but not today
// (20160121/thisisaaronland)

mapzen.whosonfirst.boundaryissues.notifications = (function() {

	var socket;
	var notification_icon = 'https://avatars1.githubusercontent.com/u/13841686?v=3&s=200';

	var self = {
		setup_websocket: function() {
			var url = mapzen.whosonfirst.boundaryissues.utils.abs_root_urlify('/ws/');
			url = url.replace(/^http:/, 'ws:');
			url = url.replace(/^https:/, 'wss:');
			socket = new WebSocket(url);
			socket.onmessage = self.handle_message;
			socket.onclose(function() {
				console.log('WebSocket: onclose');
			});
			socket.onerror(function() {
				console.log('WebSocket: onerror');
			});
			$(window).on('beforeunload', function() {
				socket.close();
			});
		},

		handle_message: function(rsp) {
			var data = JSON.parse(rsp['data']);
			if (data.details && data.details.user_ids) {

				// This is not a particularly safe or private
				// way to handle user-specific notifications,
				// but it will do for our current needs.
				// (20160603/dphiffer)

				var user_id = $(document.body).data('user-id');
				user_id = parseInt(user_id);

				if (! user_id) {
					// Not signed in
					return;
				}

				if (data.details.user_ids.indexOf(user_id) == -1) {
					// Not for us
					return;
				}
			}

			if (! data.title ||
			    ! data.body) {
				mapzen.whosonfirst.log.error('Invalid notification: ' + JSON.stringify(data));
			} else {
				self.request_notification(data);

				// Log it!
				var log_msg = data.title + ' / ' + data.body;
				if (data.details) {
					log_msg += ' / ' + JSON.stringify(data.details);
				}
				mapzen.whosonfirst.log.info(log_msg);
			}
		},

		request_notification: function(data) {
			if (! ("Notification" in window)) {
				// Not supported
				return;
			} else if (Notification.permission == "granted") {
				self.send_notification(data);
			} else if (Notification.permission != "denied") {
				Notification.requestPermission(function(permission) {
					if (permission == "granted") {
						self.send_notification(data);
					}
				});
			}
		},

		send_notification: function(data) {
			var n = new Notification(data.title, {
				body: data.body,
				icon: notification_icon
			});
		}
	};

	$(document).ready(function() {
		self.setup_websocket();
	});

	return self;

})();
