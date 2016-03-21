var mapzen = mapzen || {};
mapzen.whosonfirst = mapzen.whosonfirst || {};
mapzen.whosonfirst.leaflet = mapzen.whosonfirst.leaflet || {};

mapzen.whosonfirst.leaflet.styles = (function(){

	var self = {

		'bbox': function(){
			return {
				"color": "#000000",
				"weight": .5,
				"opacity": 1,
				"fillColor": "#000000",
				"fillOpacity": .4,
			};
		},

		'label_centroid': function(){

			return {
				"color": "#fff",
				"weight": 3,
				"opacity": 1,
				"radius": 10,
				"fillColor": "#ff0099",
				"fillOpacity": 0.8
			};
		},

		'math_centroid': function(){

			return {
				"color": "#fff",
				"weight": 2,
				"opacity": 1,
				"radius": 6,
				"fillColor": "#ff7800",
				"fillOpacity": 0.8
			};
		},

		'geom_centroid': function(){

			return {
				"color": "#fff",
				"weight": 3,
				"opacity": 1,
				"radius": 10,
				"fillColor": "#32cd32",
				"fillOpacity": 0.8
			};
		},

		'search_centroid': function(){

			return {
				"color": "#000",
				"weight": 2,
				"opacity": 1,
				"radius": 6,
				"fillColor": "#0BBDFF",
				"fillOpacity": 1
			};
		},

		'breach_polygon': function(){

			return {
				"color": "#ffff00",
				//"color": "#002EA7",
				"weight": 1.5,
				"dashArray": "5, 5",
				"opacity": 1,
				"fillColor": "#002EA7",
				"fillOpacity": 0.1
			};
		},

		'consensus_polygon': function(){

			return {
				"color": "#ff0066",
				"weight": 2,
				"opacity": 1,
				"fillColor": "#ff69b4",
				"fillOpacity": 0.6
			};
		},

		'parent_polygon': function(){

			return {
				"color": "#000",
				"weight": 1,
				"opacity": 1,
				"fillColor": "#00308F",
				"fillOpacity": 0.5
			};
		},

		'venue_current': function(){
			return {
				"color": "#000",
				"weight": 1,
				"opacity": 1,
				"radius": 4,
				"fillColor": "#5cb85c",
				"fillOpacity": 0.5
			};
		},

		'venue_not_current': function(){
			return {
				"color": "#000",
				"weight": 1,
				"opacity": 1,
				"radius": 4,
				"fillColor": "#f0ad4e",
				"fillOpacity": 0.5
			};
		},

		'venue_deprecated': function(){
			return {
				"color": "#000",
				"weight": 1,
				"opacity": 1,
				"radius": 4,
				"fillColor": "#d9534f",
				"fillOpacity": 0.5
			};
		},

		'venue_funky': function(){
			return {
				"color": "#000",
				"weight": 1,
				"opacity": 1,
				"radius": 4,
				"fillColor": "#5bc0de",
				"fillOpacity": 0.5
			};
		},

		'venue_hover': function(){
			return {
				"radius": 6,
				"fillOpacity": 1
			};
		}
	};


	return self;
})();
