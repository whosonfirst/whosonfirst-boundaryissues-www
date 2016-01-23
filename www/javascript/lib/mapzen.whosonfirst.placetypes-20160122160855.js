var mapzen = mapzen || {};
mapzen.whosonfirst = mapzen.whosonfirst || {};

// this is an early port of py-mapzen-whosonfirst-placetypes and porting
// all this code to another language may necessitate changes which is not
// the goal of this exercise but useful and all that...
// (21050911/thisisaaronland)

// also this (21050911/thisisaaronland)
// https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Operators/yield#Browser_compatibility

mapzen.whosonfirst.placetypes = (function(){

	// __spec__ was generated from: https://github.com/whosonfirst/whosonfirst-placetypes/blob/master/bin/compile.py
        // 20151209

	var __spec__ = {"102312321": {"role": "optional", "name": "microhood", "parent": [102312319], "names": {}}, "102312323": {"role": "optional", "name": "macrohood", "parent": [102312317], "names": {}}, "102312325": {"role": "common_optional", "name": "venue", "parent": [102312327, 102312329, 102312331, 102312321, 102312319], "names": {}}, "102312327": {"role": "common_optional", "name": "building", "parent": [102312329, 102312331, 102312321, 102312319], "names": {}}, "102312329": {"role": "common_optional", "name": "address", "parent": [102312331, 102312321, 102312319], "names": {}}, "102312331": {"role": "common_optional", "name": "campus", "parent": [102312321, 102312319, 102312323, 102312317, 404221409], "names": {}}, "404528653": {"role": "common_optional", "name": "ocean", "parent": [102312341], "names": {}}, "102312335": {"role": "common_optional", "name": "empire", "parent": [102312309], "names": {}}, "102312341": {"role": "common_optional", "name": "planet", "parent": [], "names": {}}, "102320821": {"role": "common_optional", "name": "dependency", "parent": [102312307], "names": {}}, "136057795": {"role": "common_optional", "name": "timezone", "parent": [102312307, 102312309, 102312341], "names": {}}, "404528655": {"role": "common_optional", "name": "marinearea", "parent": [102312307, 102312309, 102312341], "names": {}}, "102371933": {"role": "optional", "name": "metroarea", "parent": [], "names": {}}, "404221409": {"role": "common_optional", "name": "localadmin", "parent": [102312313, 102312311], "names": {}}, "404221411": {"role": "optional", "name": "macroregion", "parent": [102320821, 102322043, 102312307], "names": {}}, "404221413": {"role": "optional", "name": "macrocounty", "parent": [102312311], "names": {}}, "102312307": {"role": "common", "name": "country", "parent": [102312335, 102312309], "names": {}}, "102312309": {"role": "common", "name": "continent", "parent": [102312341], "names": {}}, "102312311": {"role": "common", "name": "region", "parent": [404221411, 102320821, 102322043, 102312307], "names": {}}, "102312313": {"role": "common_optional", "name": "county", "parent": [404221413, 102312311], "names": {}}, "102322043": {"role": "common_optional", "name": "disputed", "parent": [102312307], "names": {}}, "102312317": {"role": "common", "name": "locality", "parent": [404221409, 102312313, 102312311], "names": {}}, "102312319": {"role": "common", "name": "neighbourhood", "parent": [102312323, 102312317], "names": {"eng_p": ["neighbourhood", "neighborhood"]}}}


	var __placetypes__ = {};
	var __roles__ = {};

	for (var id in __spec__){

		var details = __spec__[id];
		var name = details['name'];
		var role = details['role'];
		var parents = [];

		var count_pids = details['parent'].length;

		for (var p=0; p < count_pids; p++){
			var pid = details['parent'][p];
			var parent = __spec__[pid];
			parents.push(parent['name']);
		}

		__placetypes__[name] = {
			'id': id,
			'role': role,
			'parent': parents
		}

		var names = details['names'] || {};
		__placetypes__[name]['names'] = names;

		for (var label in names){

			if (! label.endsWith("_p")){
				continue;
			}

			var alts = names[label];
			var count_alts = alts.length;

			for (var c=0; c < count_alts; c++){

				var alt = alts[c];

				if (! __placetypes__[alt]){
					__placetypes__[alt] = __placetypes__[name];
				}
			}
		}

		if (! __roles__[role]){
			__roles__[role] = {};
		}
	}

	var self = {
		
		'placetypename': function(label, name){

			var instance = function(label, name){
				
				var parts = label.split("_");
				var lang = parts[0];
				var kind = parts[1];

				var _self = {
					'lang': lang,
					'kind': kind,
					'name': name,

					'toString': function(){
						return _self.name;
					}
				};

				return _self;
			};

			return instance(label, name);
		},
		
		'placetype': function(pt){

			if (! self.is_valid_placetype(pt)){
				return undefined;
			}

			var instance = function(pt){

				var _self = {
					'placetype': pt,
					'details': __placetypes__[pt],

					'toString': function(){
						return _self.placetype;
					},

					'id': function(){
						return _self.details['id'];
					},

					'role': function(){
						return _self.details['role'];
					},

					'name': function(){
						return _self.placetype;
					},

					'names': function(){

						var names = [];
						var _names = _self.details['names'];

						for (var label in _names){
							var _alts = _names[label];
							var count = _alts.length;

							for (var i=0; i < count; i++){
								var ptn = mapzen.whosonfirst.placetypes.placetypename(label, _alts[i]);
								names.push(ptn);
							}
						}

						return names;
					},

					'parents': function(){
						return _self.details['parent'];
					},

					'ancestors': function(roles, ancestors){

						if (! roles){
							roles = [ 'common' ];
						}

						if (! ancestors){
							ancestors = [];
						}

						var parents = _self.parents();
						var count_parents = parents.length;

						for (var i=0; i < count_parents; i++){

							var p = parents[i];
							p = mapzen.whosonfirst.placetypes.placetype(p);

							var name = p.name();
							var role = p.role();

							if (ancestors.indexOf(name) != -1){
								continue;
							}

							if (roles.indexOf(role) == -1){
								continue;
							}

							ancestors.push(name)
							p.ancestors(roles, ancestors)
						}

						return ancestors;
					}
				};

				return _self;
			};

			return instance(pt);
		},

		'is_valid_placetype': function(pt, role){

			if (! __placetypes__[pt]){
				return false;
			}

			if ((role) && (__placetypes__[pt]['role'] != role)){
				return false;
			}

			return true;
		},

		'common': function(){
			return self.with_role('common');
		},

		'common_optional': function(){
			return self.with_role('common_optional');
		},

		'optional': function(){
			return self.with_role('optional');
		},

		'is_valid_role': function(role){

			if (! __roles__[role]){
				return false;
			}

			return true;
		},

		'with_role': function(role){
			var roles = [role];
			return self.with_roles(roles);
		},

		'with_roles': function(roles){
			
			var placetypes = [];

			for (var pt in __placetypes__){

				var details = __placetypes__[pt];
				var role = details['role'];

				if (! role){
					continue;
				}

				if (roles.indexOf(role) == -1){
					continue;
				}

				placetypes.push(pt);
			}

			return placetypes;
		},
	};

	return self;
})();
