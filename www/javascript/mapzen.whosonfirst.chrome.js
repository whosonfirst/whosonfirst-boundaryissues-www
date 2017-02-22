var mapzen = mapzen || {};
mapzen.whosonfirst = mapzen.whosonfirst || {};

mapzen.whosonfirst.chrome = (function(){

	var self = {

		'init': function() {

			var host = location.host;

			if (host == "whosonfirst.mapzen.com") {
				return;
			}

			var host_id = host.replace(".", "-");
			
			var host_el = document.createElement("div");
			host_el.setAttribute("id", "wof-host-" + host_id);
			host_el.setAttribute("class", "wof-host");

			host_el.appendChild(document.createTextNode(host));

			document.body.insertBefore(host_el, document.body.childNodes[0]);
		}
	};

	return self;
})();
