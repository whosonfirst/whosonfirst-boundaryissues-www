var mapzen = mapzen || {};
mapzen.whosonfirst = mapzen.whosonfirst || {};
mapzen.whosonfirst.boundaryissues = mapzen.whosonfirst.boundaryissues || {};

mapzen.whosonfirst.boundaryissues.namify = (function() {

	var self = {

		'init': function(){
			self.update(document);
		},

		'update': function(target){
			self.namify_wof(target);
			self.namify_brands(target);
		},

		'namify_wof': function(target){

			var els = target.getElementsByClassName("wof-namify");
			var count = els.length;

			for (var i=0; i < count; i++){
				self.namify_el(els[i], [
					self.local_resolver,
					mapzen.whosonfirst.data.id2abspath
				]);
			}
		},

		'namify_brands': function(target){

			var els = target.getElementsByClassName("wof-namify-brand");
			var count = els.length;

			for (var i=0; i < count; i++){
				self.namify_el(els[i], [
					mapzen.whosonfirst.brands.id2abspath
				]);
			}
		},

		'namify_el': function(el, resolvers){

			var wofid = el.getAttribute("data-wof-id");

			if (! wofid){	
				mapzen.whosonfirst.log.info("node is missing data-wof-id attribute");
				return;
			}

			if (el.textContent != wofid){
				mapzen.whosonfirst.log.info("node has not-a-wof-id body");
				return;
			}

			self.namify_el_from_source(wofid, el, resolvers);
		},

		'namify_el_from_source': function(wofid, el, resolvers){

			if (resolvers.length == 0){
				mapzen.whosonfirst.log.error("namifying " + wofid + ": no more resolvers to check");
				return;
			}

			var resolver = resolvers.shift();
			var url = resolver(wofid);

			var on_fetch = function(feature){
				self.apply_namification(el, feature);
			};

			var on_fail = function(rsp){
				mapzen.whosonfirst.log.info("namifying " + wofid + ": " + url + " failed, trying next resolver");
				self.namify_el_from_source(wofid, el, resolvers);
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

			var label = props['wof:label'];

			if ((! label) || (label == '')){
				label = props['wof:name'];
			}

			var enc_label = mapzen.whosonfirst.php.htmlspecialchars(label);
			el.innerHTML = enc_label;

			el.className += ' wof-namify-applied';
		},

		'local_resolver': function(wofid){
			var path = '/id/' + wofid + '.geojson';
			return mapzen.whosonfirst.boundaryissues.utils.abs_root_urlify(path);
		}
	};

	return self;

})();
