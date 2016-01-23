var mapzen = mapzen || {};
mapzen.whosonfirst = mapzen.whosonfirst || {};

mapzen.whosonfirst.net = (function(){

	var whosonfirst_cache = {};

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

		'fetch': function(url, on_success, on_fail){

			mapzen.whosonfirst.log.debug("fetch " + url);

			if (whosonfirst_cache[url]){
				
				mapzen.whosonfirst.log.debug("return " + url + " from cache");
				
				if (on_success){
					on_success(whosonfirst_cache[url]);
				}
				
				return;
			}
			
			var req = new XMLHttpRequest();
			
			req.onload = function(){
				
				try {
					var geojson = JSON.parse(this.responseText);
				}
				
				catch (e){
					mapzen.whosonfirst.log.error("failed to parse " + url + ", because " + e);

					if (on_fail){
						on_fail();
					}

					return false;
				}
				
				whosonfirst_cache[url] = geojson;
				
				if (on_success){
					on_success(geojson);
				}
			};
			
			try {				    
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

	};

	return self;

})();
