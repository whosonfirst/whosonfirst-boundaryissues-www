var mapzen = mapzen || {};
mapzen.whosonfirst = mapzen.whosonfirst || {};

mapzen.whosonfirst.brands = (function(){

	var _endpoint = "https://raw.githubusercontent.com/whosonfirst-data/whosonfirst-brands/master/data/";

	var self = {

		'endpoint': function(e){

			if (e){
				mapzen.whosonfirst.log.info("set brands endpoint to " + e);
				_endpoint = e;
			}

			return _endpoint;
		},

		'id2abspath': function (id){

			var rel_path = self.id2relpath(id);
			var abs_path = self.endpoint() + rel_path;

			return abs_path;
		},

		'id2relpath': function(id){

			parent = self.id2parent(id);
			fname = self.id2fname(id);

			var rel_path = parent + "/" + fname;
			return rel_path;
		},

		'id2parent': function(id){

			str_id = new String(id);
			tmp = new Array();

			while (str_id.length){

				var part = str_id.substr(0, 3);
				tmp.push(part);
				str_id = str_id.substr(3);
			}

			parent = tmp.join("/");
			return parent;
		},

		'id2fname': function(id){
			return id + ".json";
		}
	};

	return self;

})();
