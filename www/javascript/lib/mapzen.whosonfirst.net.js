var mapzen = mapzen || {};
mapzen.whosonfirst = mapzen.whosonfirst || {};

mapzen.whosonfirst.net = (function(){

	var default_cache_ttl = 30000; // ms

	var self = {

		'encode_query': function(query){

			enc = new Array();

			for (var k in query){
				var v = query[k];
				v = encodeURIComponent(v);
				enc.push(k + "=" + v);
			}

			return enc.join("&");
		},

		'fetch': function(url, on_success, on_fail, args){

			if (typeof(args) == "undefined") {
				args = {};
			}

			// this is here for backwards compatibility
			// (20170113/thisisaaronland)

			else if (typeof(args) == "number") {
				args = { "cache_ttl": args };
			}

			else {}

			if (args["cache_ttl"]){
				var cache_ttl = args["cache_ttl"];
			}

			else {
				var cache_ttl = default_cache_ttl;
			}

			var on_hit = function(data){
				mapzen.whosonfirst.log.debug("[cached] fetch " + url);
				if (on_success){
					on_success(data);
				}
			};

			var on_miss = function(){
				mapzen.whosonfirst.log.debug("[xhr] fetch " + url);
				self.fetch_with_xhr(url, on_success, on_fail, args);
			};

			if (! self.cache_get(url, on_hit, on_miss, cache_ttl)){
				self.fetch_with_xhr(url, on_success, on_fail, args);
			}
		},

		'fetch_with_xhr': function(url, on_success, on_fail, args){

			if (! args){
				args = {};
			}

			var req = new XMLHttpRequest();

			req.onload = function(){

				try {
					var data = JSON.parse(this.responseText);
				}

				catch (e){
					mapzen.whosonfirst.log.error("failed to parse " + url + ", because " + e);

					if (on_fail){
						on_fail();
					}

					return false;
				}

				self.cache_set(url, data);

				if (on_success){
					on_success(data);
				}
			};

			try {

				if (args["cache-busting"]){

					var cb = Math.floor(Math.random() * 1000000);

					var tmp = document.createElement("a");
					tmp.href = url;

					if (tmp.search){
						tmp.search += "&cb=" + cb;
					}

					else {
						tmp.search = "?cb= " + cb;
					}

					url = tmp.href;
				}

				// console.log("ARGS " + args);
				// console.log("URL " + url);

				req.open("get", url, true);
				req.send();
			}

			catch(e){

				mapzen.whosonfirst.log.error("failed to fetch " + url + ", because ");
				mapzen.whosonfirst.log.debug(e);

				if (on_fail){
					on_fail();
				}
			}
		},

		'cache_get': function(key, on_hit, on_miss, cache_ttl){

			if (typeof(localforage) != 'object'){
				return false;
			}

			var fq_key = self.cache_prep_key(key);

			localforage.getItem(fq_key, function (err, rsp){

				if ((err) || (! rsp)){
					on_miss();
					return;
				}

				var data = rsp['data'];

				if (! data){
					on_miss();
					return;
				}

				var dt = new Date();
				var ts = dt.getTime();

				var then = rsp['created'];
				var diff = ts - then;

				if (diff > cache_ttl){
					self.cache_unset(key);
					on_miss();
					return;
				}

				on_hit(data);
			});

			return true;
		},

		'cache_set': function(key, value){

			if (typeof(localforage) != 'object'){
				return false;
			}

			var dt = new Date();
			var ts = dt.getTime();

			var wrapper = {
				'data': value,
				'created': ts
			};

			key = self.cache_prep_key(key);

			localforage.setItem(key, wrapper);
			return true;
		},

		'cache_unset': function(key){

			if (typeof(localforage) != 'object'){
				return false;
			}

			key = self.cache_prep_key(key);

			localforage.removeItem(key);
			return true;
		},

		'cache_prep_key': function(key){
			return key + '#mapzen.whosonfirst.net';
		}
	};

	return self;

})();
