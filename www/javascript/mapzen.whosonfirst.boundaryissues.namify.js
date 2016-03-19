var mapzen = mapzen || {};
mapzen.whosonfirst = mapzen.whosonfirst || {};
mapzen.whosonfirst.boundaryissues = mapzen.whosonfirst.boundaryissues || {};

mapzen.whosonfirst.boundaryissues.namify = (function() {

    var self = {
	
	'init': function(){

	    var els = document.getElementsByClassName("wof-namify");
	    var count = els.length;

	    for (var i=0; i < count; i++){

		self.namify_el(els[i]);
	    }
	},

	'namify_el': function(el){

	    var wofid = el.getAttribute("data-wof-id");

	    if (! wofid){	
		mapzen.whosonfirst.log.info("node is missing data-wof-id attribute");
		return;
	    }

	    if (el.textContent != wofid){
		mapzen.whosonfirst.log.info("node has not-a-wof-id body");
		return;
	    }

	    var url = mapzen.whosonfirst.data.id2abspath(wofid);

	    var on_fetch = function(feature){

		var props = feature['properties'];

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
