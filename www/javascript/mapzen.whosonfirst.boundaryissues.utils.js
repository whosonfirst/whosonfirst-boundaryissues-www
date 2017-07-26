var mapzen = mapzen || {};
mapzen.whosonfirst = mapzen.whosonfirst || {};
mapzen.whosonfirst.boundaryissues = mapzen.whosonfirst.boundaryissues || {};

mapzen.whosonfirst.boundaryissues.utils = (function(){

	var self = {

		'abs_root_url': function(){
			return document.body.getAttribute("data-abs-root-url");
		},

		'abs_root_urlify': function(url){

			var root = self.abs_root_url();

			if (url.startsWith(root)){
				return url;
			}

			if (url.startsWith("/")){
				url = url.substring(1);
			}

			return root + url;
		},

		'get_meta_file': function(filename, cb){
			var url = mapzen.whosonfirst.boundaryissues.utils.abs_root_urlify('/meta/' + filename);
			var onsuccess = function(result){
				cb(result);
			};
			var onerror = function(){
				mapzen.whosonfirst.log.debug("error loading " + filename + ".");
			};
			var cache_ttl = 60 * 60 * 1000; // one hour
			return mapzen.whosonfirst.net.fetch(url, onsuccess, onerror, cache_ttl);
		},
	};

	return self

})();
