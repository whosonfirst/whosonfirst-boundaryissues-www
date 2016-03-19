var mapzen = mapzen || {};
mapzen.whosonfirst = mapzen.whosonfirst || {};
mapzen.whosonfirst.boundaryissues = mapzen.whosonfirst.boundaryissues || {};

mapzen.whosonfirst.boundaryissues.namify = (function() {

    var self = {
	
	'init': function(){
	    self.namify_wof();
	    self.namify_brands();
	},
	
	'namify_wof': function(){

	    var resolver = mapzen.whosonfirst.data.id2abspath;

	    var els = document.getElementsByClassName("wof-namify");
	    var count = els.length;

	    for (var i=0; i < count; i++){

		self.namify_el(els[i], resolver);
	    }
	},

	'namify_brands': function(){

	    var resolver = mapzen.whosonfirst.brands.id2abspath;

	    var els = document.getElementsByClassName("wof-namify-brand");
	    var count = els.length;

	    for (var i=0; i < count; i++){

		self.namify_el(els[i], resolver);
	    }
	},

	'namify_el': function(el, resolver){

	    var wofid = el.getAttribute("data-wof-id");

	    if (! wofid){	
		mapzen.whosonfirst.log.info("node is missing data-wof-id attribute");
		return;
	    }

	    if (el.textContent != wofid){
		mapzen.whosonfirst.log.info("node has not-a-wof-id body");
		return;
	    }

	    var url = resolver(wofid);

	    var on_fetch = function(feature){

		var props = feature['properties'];

		// to account for whosonfirst-brands which needs to be updated
		// to grow a 'properties' hash... (20160319/thisisaaronland)

		if (! props){
		    props = feature;
		}

		console.log(props);

		var label = props['wof:label'];

		if ((! label) || (label == '')){
		    label = props['wof:name'];
		}

		var enc_label = mapzen.whosonfirst.php.htmlspecialchars(label);
		el.innerHTML = enc_label;
	    };

	    var on_fail = function(rsp){
		console.log("sad face");
	    };
	    
	    mapzen.whosonfirst.net.fetch(url, on_fetch, on_fail);
	},
    };

    return self;

})();
