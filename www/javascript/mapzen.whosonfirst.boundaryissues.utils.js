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
	}
    };

    return self
    
})();
