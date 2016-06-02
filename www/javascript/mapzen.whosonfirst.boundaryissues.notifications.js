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
			var protocol = location.protocol == 'https:' ? 'wss:' : 'ws:';
			socket = new WebSocket(protocol + '//' + location.host + '/ws/');
			socket.onmessage = self.handle_message;
			$(window).on('beforeunload', function() {
				socket.close();
			});
		},

		handle_message: function(rsp) {
			var data = rsp['data'];
			data = JSON.parse(data);
			var message = self.format_message(data);
			if (data.action == 'execute') {
				// Only notify on 'execute'
				self.request_notification(message);
			}
			// Log everything
			mapzen.whosonfirst.log.info(data.action + ' ' + message);
		},

		format_message: function(data) {
			return data.task + ' ' + data.task_id;
		},

		request_notification: function(message) {
			if (! ("Notification" in window)) {
				return;
			} else if (Notification.permission == "granted") {
				self.send_notification(message);
			} else if (Notification.permission != "denied") {
				Notification.requestPermission(function(permission) {
					if (permission == "granted") {
						self.send_notification(message);
					}
				});
			}
		},

		send_notification: function(message) {
			var n = new Notification('Boundary Issues', {
				body: message,
				icon: notification_icon
			});
		}
	};

	$(document).ready(function() {
		self.setup_websocket();
	});

	return self;

})();
