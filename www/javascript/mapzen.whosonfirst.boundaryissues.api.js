var mapzen = mapzen || {};
mapzen.whosonfirst = mapzen.whosonfirst || {};
mapzen.whosonfirst.boundaryissues = mapzen.whosonfirst.boundaryissues || {};

// for the time being this assumes jQuery is present
// that decision may change some day but not today
// (20160121/thisisaaronland)

mapzen.whosonfirst.boundaryissues.api = (function(){

	// hey look! running code
	$.support.cors = true;

	var self = {

		'api_call': function(method, data, on_success, on_error){

			var dothis_onsuccess = function(rsp){

				if (on_success){
					on_success(rsp);
				}
			};

			var dothis_onerror = function(rsp){

				var parse_rsp = function(rsp){
					if (! rsp['responseText']){
						console.log("Missing response text");
						return;
					}
					
					try {
						rsp = JSON.parse(rsp['responseText']);
						return rsp;
					} catch (e) {
						console.log("Failed to parse response text");
						return;
					}
				};
				
				rsp = parse_rsp(rsp);
				
				if (on_error){
					on_error(rsp);
				}
			};
			
			var ima_formdata = (data.append) ? 1 : 0;
			
			if (ima_formdata){
				data.append('method', method);
				
				if (! data.access_token){
					data.append('access_token', self.api_site_token());
				}
			} else {
				data['method'] = method;
				
				if (! data['access_token']){
					data['access_token'] = self.api_site_token();
				}
			}
			
			var endpoint = self.api_endpoint();
			// console.log('endpoint is ' + endpoint);
			
			//	data.format="jsonp";
			
			var args = {
				'url': endpoint,
				'type': 'POST',
				'data': data,
				'dataType': 'json',
				'success': dothis_onsuccess,
				'error': dothis_onerror,
			};
			
			if (ima_formdata){
				args['cache'] = false;
				args['contentType'] = false;
				args['processData'] = false;
			}
			
			$.ajax(args);	    
		},

		'api_endpoint': function(){
			return $("body").data("api-endpoint");
		},

		'api_site_token': function(){
			return $("body").data("api-access-token");
		}

	};

	return self;
})();
