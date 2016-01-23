var mapzen = mapzen || {};
mapzen.whosonfirst = mapzen.whosonfirst || {};

mapzen.whosonfirst.log = (function(){

	var _log = [];

	var self = {

		'show': function(){
			self.toggle(true);
		},

		'hide': function(){
			self.toggle(false);
		},

		'toggle': function(show){

			var c = document.getElementById('wof-log-container');
			
			if (! c){
				return false;
			}

			var style = (show) ? "display:block" : "display:none";
			c.setAttribute("style", style);
		},

		'debug': function(msg){
			self.log(msg, 'debug');
		},

		'info': function(msg){
			self.log(msg, 'info');
		},

		'warning': function(msg){
			self.log(msg, 'warning');
		},

		'error': function(msg){
			self.log(msg, 'error');
		},

		'log': function(msg, cls){

			var dt = new Date();

			_log.push([cls, msg, dt]);

			var el = self._render(msg, cls, dt);
			self._attach(el);
			self.show();
		},
		
		'_render': function(msg, cls, dt){

			var enc_msg = mapzen.whosonfirst.php.htmlspecialchars(msg);
			var enc_cls = mapzen.whosonfirst.php.htmlspecialchars(cls);

			var item = document.createElement("li");
			item.setAttribute("class", "wof-log-item wof-log-" + enc_cls);

			var text = document.createTextNode(enc_msg);

			var span = document.createElement("span");
			span.setAttribute("class", "wof-log-body");
			span.appendChild(text);

			var ts = dt.toISOString();
			ts = mapzen.whosonfirst.php.htmlspecialchars(ts);
			ts = document.createTextNode(ts + " " + cls);

			var code = document.createElement("code");
			code.setAttribute("class", "wof-log-ts");
			code.appendChild(ts);

			item.appendChild(code);
			item.appendChild(span);

			return item;
		},
		
		'_attach': function(el){

			var n = document.getElementById('wof-log');

			if (! n){
				console.log("faile to locate #wof-log container");
				return false;
			}

			n.insertBefore(el, n.childNodes[0]);
			return true;
		}
	};
	
	return self;

})();
