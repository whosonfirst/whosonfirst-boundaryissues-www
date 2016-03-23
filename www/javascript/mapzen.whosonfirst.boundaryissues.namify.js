var mapzen = mapzen || {};
mapzen.whosonfirst = mapzen.whosonfirst || {};
mapzen.whosonfirst.boundaryissues = mapzen.whosonfirst.boundaryissues || {};

mapzen.whosonfirst.boundaryissues.namify = (function() {

    var cache_ttl = 30000;

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

	    var on_hit = function(feature){
		self.apply_namification(el, feature);
	    };
	    
	    var on_miss = function(){
		console.log("INVOKING ON MISS FOR " + url);
		self.namify_el_from_source(url, el);
	    };

	    if (! self.cache_get(url, on_hit, on_miss)){
		self.namify_el_from_source(url, el);
	    }

	},

	'namify_el_from_source': function(url, el){

	    var on_fetch = function(feature){

		self.apply_namification(el, feature);
		self.cache_set(url, feature);
	    };

	    var on_fail = function(rsp){
		console.log("sad face");
	    };
	    
	    mapzen.whosonfirst.net.fetch(url, on_fetch, on_fail);
	},

	'apply_namification': function(el, feature){

		var props = feature['properties'];

		// to account for whosonfirst-brands which needs to be updated
		// to grow a 'properties' hash... (20160319/thisisaaronland)

		if (! props){
		    props = feature;
		}

	    	// console.log(props);
	    
		var label = props['wof:label'];

		if ((! label) || (label == '')){
		    label = props['wof:name'];
		}

		var enc_label = mapzen.whosonfirst.php.htmlspecialchars(label);
		el.innerHTML = enc_label;
	},

	'cache_get': function(key, on_hit, on_miss){

	    if (typeof(localforage) != 'object'){
		return false;
	    }

	    var fq_key = self.cache_prep_key(key);

	    localforage.getItem(fq_key, function (err, rsp){

		if ((err) || (! rsp)){
		    console.log("cache MISS for " + fq_key);
		    on_miss();
		}

		console.log("cache HIT for " + fq_key);
		console.log(rsp);

		var data = rsp['data'];

		if (! data){
		    console.log("cache WTF for " + fq_key);
		    on_miss();
		}

		var dt = new Date();
		var ts = dt.getTime();

		var then = rsp['created'];
		var diff = ts - then;

		if (diff > cache_ttl){
		    console.log("cache EXPIRED for " + fq_key);
		    self.cache_unset(key);
		    on_miss();
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
	    console.log("cache SET for " + key);

	    localforage.setItem(key, wrapper);
	    return true;
	},

	'cache_unset': function(key){

	    if (typeof(localforage) != 'object'){
		return false;
	    }

	    key = self.cache_prep_key(key);
	    console.log("cache UNSET for " + key);

	    localforage.removeItem(key);
	    return true;
	},

	'cache_prep_key': function(key){
	    return key + '#namify';
	}
    };

    return self;

})();
