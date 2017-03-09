var mapzen = mapzen || {};
mapzen.whosonfirst = mapzen.whosonfirst || {};

mapzen.whosonfirst.uri = (function(){

	var _endpoint = "https://whosonfirst.mapzen.com/data/";

	var self = {

		'endpoint': function(e){

			if (e){
				mapzen.whosonfirst.log.info("set uri endpoint to " + e);
				_endpoint = e;
			}

			return _endpoint;
		},

		'id2abspath': function (id, args){
		    
		    var rel_path = self.id2relpath(id, args);
		    var abs_path = self.endpoint() + rel_path;
		    
		    return abs_path;
		},

		'id2relpath': function(id, args){

			parent = self.id2parent(id);
			fname = self.id2fname(id, args);

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

		'id2fname': function(id, args){

		    if (! args){
			args = {};
		    }

		    var fname = [
			encodeURIComponent(id)
		    ];

		    if (args["alt"]) {

			if (args["source"]){

			    // to do: validate source here
			    // to do: actually write mapzen.whosonfirst.source.js
			    // (20161130/thisisaaronland)

			    var source = encodeURIComponent(args["source"]);
			    fname.push(source);

			    if (args["function"]){

				var func = encodeURIComponent(args["function"]);
				fname.push(func);

				if ((args["extras"]) && (args["extras"].join)){

				    var extras = args["extras"];
				    var count = extras.length;

				    for (var i = 0; i < count; i++){
					var extra = encodeURIComponent(extras[i]);
					fname.push(extra);
				    }
				}
			    }
			}

			else {
			    console.log("missing source parameter for alternate geometry");
			    fname.push("unknown");
			}
			
		    }

		    var str_fname = fname.join("-");

		    return str_fname + ".geojson";
		}
	};

	return self;

})();
