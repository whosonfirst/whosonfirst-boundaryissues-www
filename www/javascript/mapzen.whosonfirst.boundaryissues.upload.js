var mapzen = mapzen || {};
mapzen.whosonfirst = mapzen.whosonfirst || {};
mapzen.whosonfirst.boundaryissues = mapzen.whosonfirst.boundaryissues || {};

// for the time being this assumes jQuery is present
// that decision may change some day but not today
// (20160121/thisisaaronland)

mapzen.whosonfirst.boundaryissues.upload = (function(){

	var $form,
	    $result,
	    $preview_map,
	    $preview_props,
	    geojson_file,
	    csv_file,
	    csv_row_count,
	    csv_preview_row = 1,
	    zip_file,
	    upload_is_ready = false,
	    properties_are_ready = false,
	    is_collection,
	    is_csv,
	    is_zip,
	    feature_count,
	    VenueIcon,
	    poi_icon_base,
	    geotagged;

	var esc_str = mapzen.whosonfirst.php.htmlspecialchars;

	var self = {

		setup_upload: function(){

			// Get some jQuery references to our top-level elements
			$form = $('#upload-form');
			$result = $('#upload-result');
			$preview_map = $('#upload-preview-map');
			$preview_props = $('#upload-preview-props');

			var preview_handler = function(file, cb) {
				if (file && is_zip) {
					JSZip.loadAsync(file).then(function(zip) {
						cb(file, zip);
					});
				} else if (file) {
					var reader = new FileReader();
					reader.onload = function() {
						cb(reader.result);
					};
					reader.readAsText(file);
				} else {
					mapzen.whosonfirst.log.error('No file to preview.');
				}
			};

			var preview_error = function(msg) {
				$('#upload-status').html('<small class="caveat">' + msg + '</small>');
			};

			// Preview the GeoJSON when the file input's onchange fires
			$form.find('input[name=file]').on('change', function(e) {
				var formats = $(e.target).data('formats');
				var format_list = formats.match(/\.\w+/g);
				if (e.target.files.length == 1) {
					var ext = e.target.files[0].name.match(/\.\w+$/);
					if (ext.length < 1 ||
					    format_list.indexOf(ext[0].toLowerCase()) == -1) {
						preview_error('Please upload files with one of <strong>' + formats + '</strong> file extension');
						return;
					}

					ext = ext[0].toLowerCase();
					if (ext == '.geojson') {
						geojson_file = e.target.files[0];
						preview_handler(geojson_file, self.preview_geojson);
					} else if (ext == '.csv') {
						is_csv = true;
						csv_file = e.target.files[0];
						preview_handler(csv_file, self.preview_csv);
					} else if (ext == '.zip') {
						is_zip = true;
						zip_file = e.target.files[0];
						preview_handler(zip_file, self.preview_zip);
					} else if (ext == '.jpg' || ext == '.jpeg') {
						mapzen.whosonfirst.geotagged.init([e.target.files[0]], self.preview_geotagged, preview_error);
					}
				} else if (e.target.files.length > 0) {
					var photos = [];
					for (var i = 0; i < e.target.files.length; i++) {
						if (e.target.files[i].name.match(/\.jpe?g$/i)) {
							photos.push(e.target.files[i]);
						}
					}

					if (photos.length == 0) {
						// welp, there are more than one file, but none are JPEGs
						preview_error('Please upload only <strong>one file at a time</strong>');
						return;
					}

					mapzen.whosonfirst.geotagged.init(photos, self.preview_geotagged, preview_error);
				} else {
					$('#upload-status').html('<small>Accepted formats: ' + format_list.join(', ') + '</small>');
				}
			});

			// Intercept the form submit event and upload the file via API
			$form.submit(function(e){
				e.preventDefault();
				if (! upload_is_ready ||
				    ! properties_are_ready) {
					return;
				}
				if (geotagged) {
					self.save_geotagged();
				} else {
					self.post_file();
				}
			});

			// Listen for updates on FeatureCollection uploads
			$(document.body).on('notification', function(e, data) {
				self.collection_update(data);
			});
		},

		setup_property_aliases: function(){
			var url = mapzen.whosonfirst.boundaryissues.utils.abs_root_urlify('/meta/property_aliases.json');
			var onsuccess = function(aliases){
				self.property_aliases = aliases;
				properties_are_ready = true;
			};
			var onerror = function(){
				mapzen.whosonfirst.log.debug("error loading property aliases.");
			};
			var cache_ttl = 60 * 60 * 1000; // one hour
			mapzen.whosonfirst.net.fetch(url, onsuccess, onerror, cache_ttl);
		},

		preview_geojson: function(data){

			try {
				var geojson = JSON.parse(data);
			} catch(e) {
				$result.html(e);
				upload_is_ready = false;
				return;
			}

			$result.html('This is just a preview. You still have to hit the upload button.');

			upload_is_ready = true;
			$('#upload-btn').addClass('btn-primary');
			$('#upload-btn').attr('disabled', false);

			var map = self.setup_map_preview(geojson);

			if (geojson.type == "Feature") {
				is_collection = false;
				if (map) {
					mapzen.whosonfirst.leaflet.fit_map(map, geojson);
					self.show_feature_preview(map, geojson);
				}
				self.show_props_preview(geojson);
			} else if (geojson.type == "FeatureCollection") {
				is_collection = true;
				if (map) {
					self.show_collection_preview(map, geojson);
				}
				self.show_props_preview(geojson);
			}
		},

		preview_csv: function(data) {

			try {
				var csv = Papa.parse(data);
			} catch(e) {
				$result.html(e);
				upload_is_ready = false;
				return;
			}

			function update_row_count() {
				// Oh, this is simple right? Just use csv.data.length
				csv_row_count = csv.data.length;

				// ... not so fast

				// What about the header row, do we count that?
				var header_row = $('#csv-headers')[0].checked;
				if (header_row) {
					csv_row_count--;
				}

				// What about trailing line breaks?
				for (var i = csv.data.length - 1; i > -1; i--) {
					if (csv.data[i].length == 1 &&
					    csv.data[i][0] == "") {
						csv_row_count--;
					} else {
						break;
					}
				}
			}

			function update_preview() {
				$('#csv-preview-status').html(csv_preview_row + ' / ' + csv_row_count);
				var index = csv_preview_row - 1;
				if ($('#csv-headers')[0].checked) {
					index++;
				}
				var row = csv.data[index];
				$('td.preview').each(function(i, td) {
					var value = htmlspecialchars(row[i]);
					$(td).html(value);
				});
			}

			$result.html('Please choose which CSV column maps onto which Who’s On First property.<br><small class="caveat">at minimum we will need a <span class="hey-look">wof:name</span> and <em>either</em> a <span class="hey-look">addr:full</span> or pair of <span class="hey-look">geom:latitude</span> + <span class="hey-look">geom:longitude</span> coordinates</small>');

			var selected_attr = function(i, j) {
				if (i == j) {
					return ' selected="selected"';
				}
				return '';
			}

			var property_select_options = function(default_value, index_selected) {
				var options = '';
				options += '<option value=""' + selected_attr(0, index_selected) + '>(ignore column)</option>';
				options += '<option' + selected_attr(1, index_selected) + '>' + default_value + '</option>';
				options += '<option' + selected_attr(2, index_selected) + '>Concordance...</option>';
				options += '<option' + selected_attr(3, index_selected) + '>Custom...</option>';
				options += '<option' + selected_attr(4, index_selected) + '>wof:id</option>';
				options += '<option' + selected_attr(5, index_selected) + '>wof:name</option>';
				options += '<option' + selected_attr(6, index_selected) + '>geom:latitude</option>';
				options += '<option' + selected_attr(7, index_selected) + '>geom:longitude</option>';
				options += '<option' + selected_attr(8, index_selected) + '>addr:full</option>';
				options += '<option' + selected_attr(9, index_selected) + '>addr:housenumber</option>';
				options += '<option' + selected_attr(10, index_selected) + '>addr:street</option>';
				options += '<option' + selected_attr(11, index_selected) + '>addr:housenumber addr:street</option>';
				options += '<option' + selected_attr(12, index_selected) + '>addr:postcode</option>';
				options += '<option' + selected_attr(13, index_selected) + '>addr:city</option>';
				options += '<option' + selected_attr(14, index_selected) + '>addr:state</option>';
				options += '<option' + selected_attr(15, index_selected) + '>addr:province</option>';
				options += '<option' + selected_attr(16, index_selected) + '>addr:phone</option>';
				options += '<option' + selected_attr(17, index_selected) + '>wof:tags</option>';
				options += '<option' + selected_attr(18, index_selected) + '>edtf:inception</option>';
				options += '<option' + selected_attr(19, index_selected) + '>edtf:cessation</option>';
				return options;
			}

			var property_select_html = function(column, default_index) {

				var name = 'property-' + htmlspecialchars(column);
				var default_value = 'misc:' + column.trim().replace(/\W/, '_');

				if (typeof default_index != 'undefined') {
					index_selected = default_index;
					if (column == 'wof_id') {
						index_selected = 4;
					}
				} else {
					var index_selected = 1;
					if (column == 'wof_id') {
						index_selected = 4;
					} else if (column == 'name') {
						index_selected = 5;
					} else if (column == 'latitude' ||
					           column == 'lat') {
						index_selected = 6;
					} else if (column == 'longitude' ||
					           column == 'long' ||
					           column == 'lng' ||
					           column == 'lon') {
						index_selected = 7;
					} else if (column == 'address') {
						index_selected = 8;
					} else if (column == 'city') {
						index_selected = 13;
					} else if (column == 'state') {
						index_selected = 14;
					}
				}

				var html = '<select name="' + name + '" data-column="' + htmlspecialchars(column) + '" class="column">';
				html += property_select_options(default_value, index_selected);
				html += '</select>';

				return html;
			};

			var check_if_ready = function() {
				var has_id = false;
				var has_name = false;
				$('#upload-preview-props select').each(function(i, select) {
					if ($(select).val() == 'wof:id') {
						has_id = true;
					} else if ($(select).val() == 'wof:name') {
						has_name = true;
					}
				});
				if (has_id || has_name) {
					upload_is_ready = true;
					$('#upload-btn').addClass('btn-primary');
					$('#upload-btn').attr('disabled', false);
				} else {
					upload_is_ready = false;
					$('#upload-btn').removeClass('btn-primary');
					$('#upload-btn').attr('disabled', 'disabled');
				}
			};

			var csv_controls = '<div id="csv-controls">';
			csv_controls += '<div class="input-group">';
			csv_controls += '<input type="checkbox" name="csv_headers" id="csv-headers" checked="checked">';
			csv_controls += ' <label for="csv-headers">First CSV row is column headers</label>';
			csv_controls += '</div>';
			csv_controls += '<div class="input-group">';
			csv_controls += '<label for="geometry-source">Geometry source</label>';
			csv_controls += '<input type="text" id="geometry-source" size="10" value="mapzen">';
			csv_controls += '<span id="geometry-source-link"></span>';
			csv_controls += '</div>';
			csv_controls += '<div class="input-group">';
			csv_controls += '<label for="property-prefix">Property prefix</label>';
			csv_controls += '<input type="text" id="property-prefix" size="10" value="misc">';
			csv_controls += '</div>';
			csv_controls += '<div class="input-group">';
			csv_controls += '<label for="common-tags">Common tags</label>';
			csv_controls += '<input type="text" id="common-tags" size="30" value="">';
			csv_controls += '</div>';
			csv_controls += '</div>';

			var table = '<table id="csv-columns">';
			table += '<tr><th class="column">Column header</th>';
			table += '<th class="property">WOF property</th>';
			table += '<th class="preview">Preview values ';
			table += '<a href="#csv-prev" id="csv-prev"><span class="glyphicon glyphicon-chevron-left"></span></a> ';
			table += '<span id="csv-preview-status"></span> ';
			table += '<a href="#csv-next" id="csv-next"><span class="glyphicon glyphicon-chevron-right"></span></a>';
			table += '</th></tr>';

			var headers = csv.data[0];

			// If there is a 'wof_id' column, default to "ignore"
			for (var i = 0; i < headers.length; i++) {
				if (headers[i] == 'wof_id') {
					var default_index = 0;
					break;
				}
			}

			for (var i = 0; i < headers.length; i++) {
				var column = headers[i];
				var property_select = property_select_html(column, default_index);
				table += '<tr>';
				table += '<td class="column">' + htmlspecialchars(column) + '</td>';
				table += '<td class="property">' + property_select + '</td>';
				table += '<td class="preview">' + htmlspecialchars(csv.data[1][i]) + '</td>';
				table += '</tr>';
			}

			table += '</table>';
			$('#upload-preview-props').html(csv_controls + table);

			$('#upload-preview-props select').change(function(e) {
				var select = e.target;
				var default_value = select.options[1].value;
				if (select.selectedIndex == 2) {
					var concordance_id = prompt('How would you like to store this concordance?', default_value);
					if (concordance_id) {
						var html = $(select).html();
						var option = '<option>Concordance: ' + htmlspecialchars(concordance_id) + '</option>';
						html = html.replace(/<option[^>]*>Concordance[^<]+?<\/option>/, option);
						$(select).html(html);
						select.selectedIndex = 2;
					}
				} else if (select.selectedIndex == 3) {
					var concordance_id = prompt('What WOF property would you like to use?', default_value);
					if (concordance_id) {
						var html = $(select).html();
						var option = '<option>Custom: ' + htmlspecialchars(concordance_id) + '</option>';
						html = html.replace(/<option[^>]*>Custom[^<]+?<\/option>/, option);
						$(select).html(html);
						select.selectedIndex = 3;
					}
				}
				return true;
			});

			check_if_ready();
			update_row_count();
			update_preview();

			$('#upload-preview-props select').change(check_if_ready);
			$('#csv-headers').change(function() {
				if ($('#csv-headers')[0].checked) {
					$('#csv-columns').removeClass('no-headers');
				} else {
					$('#csv-columns').addClass('no-headers');
				}
				update_row_count();
				update_preview();
			});
			$('#csv-next').click(function(e) {
				e.preventDefault();
				csv_preview_row++;
				if (csv_preview_row > csv_row_count) {
					csv_preview_row = 1;
				}
				update_preview();
			});
			$('#csv-prev').click(function(e) {
				e.preventDefault();
				csv_preview_row--;
				if (csv_preview_row == 0) {
					csv_preview_row = csv_row_count;
				}
				update_preview();
			});

			function update_property_prefix() {
				var prefix = $('#property-prefix').val();
				if (prefix == '') {
					prefix = 'misc';
					$('#property-prefix').val(prefix);
					return;
				}
				$('select.column').each(function(i, select) {
					var column = $(select).data('column');
					if (typeof column == 'string') {
						column = column.trim().replace(/\W/, '_');
					}
					var default_value = prefix + ':' + column;
					var options = property_select_options(default_value, select.selectedIndex);
					$(select).html(options);
				});
			}
			$('#property-prefix').change(update_property_prefix);

			function lookup_source() {
				var source_id = $('#geometry-source').val();
				if (source_id == '') {
					$('#geometry-source').html('');
					return;
				}

				function onsuccess(rsp) {
					var found = false;
					$.each(rsp, function(id, spec) {
						if (spec.name == source_id) {
							found = true;
							var link = htmlspecialchars(spec.fullname);
							if (spec.url) {
								link = '<a href="https://github.com/whosonfirst/whosonfirst-sources/tree/master/sources#' + htmlspecialchars(source_id) + '">' + link + '</a>';
							}
							$('#geometry-source-link').html('Known source: ' + link);
							$('#property-prefix').val(spec.prefix);
							update_property_prefix();
						}
					});
					if (! found) {
						$('#geometry-source-link').html('<i>Unknown source</i> (see: <a href="https://github.com/whosonfirst/whosonfirst-sources/tree/master/sources#sources">whosonfirst-sources</a>)');
					}
				}

				function onerror(rsp) {
					$('#geometry-source-link').html('Error looking up source');
					mapzen.whosonfirst.log.error(rsp);
				}

				var sources_url = mapzen.whosonfirst.boundaryissues.utils.abs_root_urlify('/meta/sources.json');
				var cache_ttl = 12 * 60 * 60 * 1000; // 12 hours
				mapzen.whosonfirst.net.fetch(sources_url, onsuccess, onerror, cache_ttl);
			}

			lookup_source();
			$('#geometry-source').change(lookup_source);
		},

		preview_zip: function(file, zip) {
			var name = file.name;
			var match = name.match(/^(.+)\.zip$/);
			var dir = match[1];
			var files = [];
			for (var path in zip.files) {
				if (path.substr(0, dir.length) == dir) {
					files.push(path);
				}
			}

			var html = self.pipeline_controls();
			html += '<p><strong>' + file.name + '</strong></p>';
			html += '<ul>';
			for (var i = 0; i < files.length; i++) {
				var filename = files[i].substr(dir.length + 1);
				if (filename != '') {
					html += '<li>' + filename + '</li>';
				}
				if (filename == 'meta.json') {
					zip.file(dir + '/meta.json')
					   .async('string')
					   .then(function(meta_json) {
						self.meta = JSON.parse(meta_json);
						if (self.meta.type) {
							$('#pipeline-type').val(self.meta.type);
							self.pipeline_options(self.meta.type);
						}
					});
				}
			}
			html += '</ul>';
			$('#upload-preview-props').html(html);

			$('#pipeline-type').change(function() {
				var type = $('#pipeline-type').val();
				self.pipeline_options(type);
			});
		},

		preview_geotagged: function(rsp) {
			if (! rsp.length) {
				return;
			}
			geotagged = rsp;
			var html = '';
			for (var i = 0; i < geotagged.length; i++) {
				var g = geotagged[i];
				if (g.orientation) {
					var orientation = g.orientation.replace('"', '');
				} else {
					var orientation = '';
				}
				html += '<div class="geotagged thumbnail square ' + orientation + '"></div>';
			}
			$('#upload-preview-props').html(html);
			$('#upload-preview-props .geotagged').each(function(i, div) {
				var data_uri = geotagged[i].data_uri.replace('"', '');
				$(div).css('background-image', 'url("' + data_uri + '")');
			});

			upload_is_ready = true;
			$('#upload-btn').addClass('btn-primary');
			$('#upload-btn').attr('disabled', false);
		},

		pipeline_controls: function() {

			var types = [
				'meta_files',
				'neighbourhood',
				'remove_properties',
				'fix_property_type'
			];

			var html = '<div class="input-group">';
			html += '<label for="pipeline-type">Pipeline type</label>';
			html += '<select id="pipeline-type">';
			html += '<option>Select a pipeline type...</option>';
			for (var i = 0; i < types.length; i++) {
				html += '<option>' + types[i] + '</option>';
			}
			html += '</select>';
			html += '</div>';

			html += '<div id="pipeline-options"></div>';

			return html;
		},

		pipeline_options: function(type) {

			if (self.meta) {
				var meta = self.meta;
			} else {
				var meta = {
					slack_handle: '',
					generate_meta_files: false
				};
			}

			var html = '';
			if (type == 'meta_files') {

				var repo = meta.repo || 'whosonfirst-data';

				html += '<div class="input-group">';
				html += '<label for="repo">Repo to build meta files for</label>';
				html += '<input type="text" id="repo" value="' + htmlspecialchars(repo) + '">';
				html += '</div>';

			} else if (type == 'neighbourhood') {

				var process_venues_checked = meta.process_venues ? ' checked="checked"' : '';

				html = '<p>Your zip file should include a selection of GeoJSON FeatureCollection files.</p>';
				html += '<div class="input-group">';
				html += '<input type="checkbox" id="process_venues"' + process_venues_checked + '>';
				html += '<label for="process_venues">Process descendant venues</label>';
				html += '</div>';
			} else if (type == 'remove_properties') {

				var property_list = meta.property_list || '';

				html = '<p>Your zip file should include a CSV file called <code>remove_properties.csv</code>.</p>';
				html += '<div class="input-group">';
				html += '<label for="property_list">Properties to remove (comma-separated)</label>';
				html += '<input type="text" id="property_list" value="' + htmlspecialchars(property_list) + '">';
				html += '</div>';
			} else if (type == 'fix_property_type') {

				var property = meta.property || '';
				var property_type = meta.property_type || '';

				html += '<div class="input-group">';
				html += '<label for="property">Property to check</label>';
				html += '<input type="text" id="property" value="' + htmlspecialchars(property) + '">';
				html += '</div>';

				html += '<div class="input-group">';
				html += '<label for="property">Property type</label>';
				html += '<input type="text" id="property_type" value="' + htmlspecialchars(property_type) + '">';
				html += '</div>';
			} else {
				$('#pipeline-options').html('Unknown pipeline type: ' + type);
				return false;
			}

			var default_slack_handle = $('#upload-form').data('slack-handle');
			var slack_handle = meta.slack_handle || default_slack_handle || '';
			var meta_files_checked = meta.generate_meta_files ? ' checked="checked"' : '';

			html += '<div class="input-group">';
			html += '<label for="slack_handle">Ping my Slack handle when finished</label>';
			html += '<input type="text" id="slack_handle" value="' + htmlspecialchars(slack_handle) + '">';
			html += '</div>';

			$('#pipeline-options').html(html);

			upload_is_ready = true;
			$('#upload-btn').addClass('btn-primary');
			$('#upload-btn').attr('disabled', false);
		},

		get_meta_json: function() {
			var meta = self.meta || {
				slack_handle: '',
				generate_meta_files: false
			};

			meta.type = $('#pipeline-type').val();
			meta.slack_handle = $('#slack_handle').val();
			//meta.generate_meta_files = $('#generate_meta_files')[0].checked;

			if (meta.type == 'neighbourhood') {
				meta.process_venues = $('#process_venues')[0].checked;
			} else if (meta.type == 'remove_properties') {
				meta.property_list = $('#property_list').val();
			}

			return JSON.stringify(meta);
		},

		setup_map_preview: function(geojson) {

			if (! self.has_geometry(geojson)) {
				return null;
			}

			$preview_map.removeClass('hidden');

			var scene  = mapzen.whosonfirst.boundaryissues.utils.abs_root_urlify('/tangram/refill.yaml');
			mapzen.whosonfirst.leaflet.tangram.scenefile(scene);

			// For now we just assume everyone using BI can read English. This
			// should be configurable. (20170704/dphiffer)
			mapzen.whosonfirst.leaflet.tangram.scene_options({
				ux_language: 'en'
			});

			var swlat = 37.70120736474139;
			var swlon = -122.68707275390624;
			var nelat = 37.80924146650164;
			var nelon = -122.21912384033203;

			// Show the preview map
			var map = mapzen.whosonfirst.leaflet.tangram.map_with_bbox(
				'upload-preview-map',
				swlat, swlon, nelat, nelon
			);

			// Clear the map if a feature is already on there
			map.eachLayer(function(layer) {
				if (layer.feature) {
					map.removeLayer(layer);
				}
			});

			return map;
		},

		show_collection_preview: function(map, collection) {
			mapzen.whosonfirst.leaflet.fit_map(map, collection);
			var feature;
			for (var i = 0; i < collection.features.length; i++) {
				feature = collection.features[i];
				self.show_feature_preview(map, feature);
			}
		},

		show_feature_preview: function(map, feature) {
			if (feature.properties['wof:placetype'] == 'venue') {
				var lat = parseFloat(feature.properties['geom:latitude']);
				var lng = parseFloat(feature.properties['geom:longitude']);
				marker = new L.Marker([lat, lng], {
					icon: new VenueIcon()
				}).addTo(map);
			} else {
				var style = mapzen.whosonfirst.leaflet.styles.consensus_polygon();
				mapzen.whosonfirst.leaflet.draw_poly(map, feature, style);
			}
		},

		show_props_preview: function(geojson) {
			var props = self.get_geojson_props(geojson);
			if (! props) {
				return;
			}
			var groups = [];
			var group_props = {};
			$.each(props, function(i, orig_prop) {
				var prop = self.get_translated_property(orig_prop);
				var group = '_no_group_';
				var group_match = prop.match(/^([a-z0-9_]+)\:/i);
				if (group_match) {
					group = group_match[1];
				}
				if (! group_props[group]) {
					group_props[group] = [];
					groups.push(group);
				}
				var html = self.get_property_html(prop, orig_prop);
				group_props[group].push(html);
			});

			groups.sort();
			if (! group_props._no_group_) {
				group_props._no_group_ = [];
				groups.push('_no_group_');
			} else {
				// Always put the "Other" properties last
				groups.shift();
				groups.push('_no_group_');
			}

			var html = '';

			if (self.has_geometry(geojson)) {
				html += '<div class="headroom"><input type="checkbox" class="property" id="upload-geometry" name="geometry" value="1" checked="checked"><label for="upload-geometry">Update geometry</label></div>';
			}

			$.each(groups, function(i, group) {
				if (group != '_no_group_' &&
				    group_props[group].length == 1) {
					group_props._no_group_.push(group_props[group][0]);
					return;
				} else if (group_props[group].length == 0) {
					return;
				}
				group_props[group].sort();
				html += '<div class="property-group col-md-4 headroom">';
				var group_select = '<input type="checkbox" id="group-select-' + group + '">';
				var group_text = (group == '_no_group_') ? 'Other properties' : group + ' properties';
				var group_label = '<label for="group-select-' + group + '">' + group_text + '</label>';
				html += '<div class="group-select">' + group_select + group_label + '</div>';
				html += '<ul class="upload-properties">';
				$.each(group_props[group], function(i, item) {
					html += '<li>' + item + '</li>';
				});
				html += '</ul>';
				html += '</div>';
			});

			$preview_props.html(html);
			$preview_props.find('.group-select input').change(function(e) {
				var parent = $(e.target).closest('.property-group');
				parent.find('.upload-properties input').each(function(i, checkbox) {
					checkbox.checked = e.target.checked;
				});
			});
		},

		get_geojson_props: function(geojson) {
			if (geojson.type == 'Feature') {
				return Object.keys(geojson.properties);
			} else if (geojson.features) {
				var properties = [];
				$.each(geojson.features, function(i, feature) {
					var keys = Object.keys(feature.properties);
					$.each(keys, function(j, key) {
						if (properties.indexOf(key) == -1) {
							properties.push(key);
						}
					});
				});
				return properties;
			}
			return null;
		},

		get_translated_property: function(prop) {
			if (self.property_aliases[prop]) {
				return self.property_aliases[prop];
			} else {
				return prop;
			}
		},

		get_property_html: function(prop, orig_prop) {
			var attrs = '';
			var hidden = '';
			var prop_esc = esc_str(prop);
			var orig_esc = esc_str(orig_prop);
			var aside = '';
			if (prop != orig_prop) {
				aside = ' <small><i>' + orig_esc + '</i></small>';
				hidden = '<input type="hidden" name="property_aliases[' + orig_esc + ']" value="' + prop_esc + '">';
			}
			if (prop == 'wof:id') {
				attrs = ' checked="checked" disabled="disabled"';
			}
			return '<input type="checkbox" class="property" id="property-' + orig_esc + '" name="properties[]" value="' + prop_esc + '"' + attrs + '><label for="property-' + orig_esc + '"><code>' + prop_esc + '</code>' + aside + '</label>' + hidden;
		},

		post_file: function() {

			if (! upload_is_ready ||
			    ! properties_are_ready) {
				return;
			}

			var onsuccess = function(rsp) {
				if (is_csv && rsp.csv_id) {
					window.location = mapzen.whosonfirst.boundaryissues.utils.abs_root_urlify('/csv/' + rsp.csv_id + '/1/');
				} else if (is_zip && rsp.pipeline_id) {
					window.location = mapzen.whosonfirst.boundaryissues.utils.abs_root_urlify('/pipeline/' + rsp.pipeline_id + '/');
				} else {
					self.show_result(rsp);
					mapzen.whosonfirst.log.debug(rsp);
				}
			};
			var onerror = function(rsp) {
				self.show_result(rsp);
				mapzen.whosonfirst.log.error(rsp);
			};

			var upload_file;

			if (is_csv) {

				if (csv_file) {
					upload_file = csv_file;
				} else {
					mapzen.whosonfirst.log.error('No csv_file to post.');
					return;
				}

				var crumb = $form.data("crumb-upload-csv");
				var api_method = 'wof.upload_csv';

			} else if (is_zip) {

				if (zip_file) {
					upload_file = zip_file;
				} else {
					mapzen.whosonfirst.log.error('No zip_file to post.');
					return;
				}

				var crumb = $form.data("crumb-upload-zip");
				var api_method = 'wof.upload_zip';

			} else {

				if (geojson_file) {
					upload_file = geojson_file;
				} else {
					mapzen.whosonfirst.log.error('No geojson_file to post.');
					return;
				}

				if (is_collection) {
					var crumb = $form.data("crumb-upload-collection");
					var api_method = 'wof.upload_collection';
				} else {
					var crumb = $form.data("crumb-upload-feature");
					var api_method = 'wof.upload_feature';
				}
			}

			// Assemble our form data and send it along to the API method
			var data = new FormData();
			data.append('crumb', crumb);
			data.append('upload_file', upload_file);

			if (is_csv) {

				var properties = [];
				$('select.column').each(function(i, select) {
					var property = $(select).val();
					properties.push(property);
				});
				properties = properties.join(',');

				var has_headers = $('#csv-headers')[0].checked ? 1 : 0;

				data.append('column_properties', properties);
				data.append('row_count', csv_row_count);
				data.append('has_headers', has_headers);
				data.append('geom_source', $('#geometry-source').val());
				data.append('common_tags', $('#common-tags').val());

			} else if (is_zip) {

				data.append('meta_json', self.get_meta_json());

			} else {

				var empty = true;

				if ($('#upload-geometry').length > 0 &&
				    $('#upload-geometry').get(0).checked) {
					data.append('geometry', 1);
					empty = false;
				}
				$('.upload-properties input').each(function(i, prop) {
					if ($(prop).attr('name') == 'properties[]' &&
					    prop.checked) {
						var name = $(prop).attr('name');
						var value = $(prop).val();
						data.append(name, value);
						if (value != 'wof:id') {
							empty = false;
						}
					}
				});

				if (empty) {
					$result.html("You haven't selected anything to update.");
					return;
				}
			}

			mapzen.whosonfirst.boundaryissues.api.api_call(api_method, data, onsuccess, onerror);

			// Show some user feedback
			$result.html('Uploading...');
		},

		save_geotagged: function() {
			mapzen.whosonfirst.geotagged.save_to_localforage(geotagged, function(id) {
				var url = mapzen.whosonfirst.boundaryissues.utils.abs_root_urlify('/venue/?geotagged=' + id);
				window.location = url;
			});
		},

		show_result: function(rsp) {
			if (rsp.ok) {
				var list = '<ul id="saved-wof"></ul>';
				if (rsp.collection_uuid) {
					var esc_uuid = esc_str(rsp.collection_uuid);
					$result.html('<span id="collection-' + esc_uuid + '">Processing features...</span> ' + list);
				} else if (rsp.feature) {
					$result.html('Success! ' + list);
					var wof_id = rsp.feature.properties['wof:id'];
					var wof_name = rsp.feature.properties['wof:name'];
					self.show_saved_wof_result(wof_id, wof_name);
					mapzen.whosonfirst.log.debug(rsp);
				}
			} else if (rsp.error && rsp.error.message) {
				$result.html('Error: ' + rsp.error.message);
				mapzen.whosonfirst.log.error(rsp.error.message);
			} else if (rsp.error && typeof rsp.error == 'string') {
				$result.html('Error: ' + rsp.error);
				mapzen.whosonfirst.log.error(rsp.error);
			} else if (rsp.errors) {
				$result.html('<div class="alert alert-danger">Errors:<ul><li>' + rsp.errors.join('</li><li>') + '</li></ul></div>');
				mapzen.whosonfirst.log.error(rsp.errors.join(', '));
			} else {
				$result.html('Oh noes, an error! Check the JavaScript console?');
				mapzen.whosonfirst.log.error(rsp);
			}
		},

		show_saved_wof_result: function(id, name) {
			var esc_id = esc_str(id);
			var esc_name = esc_str(name);
			var url = mapzen.whosonfirst.boundaryissues.utils.abs_root_urlify('/id/' + esc_id);
			$('#saved-wof').append('<li><a href="' + url + '"><code>' + esc_id + '</code> ' + esc_name + '</a></li>');
		},

		collection_update: function(data) {
			if (data.feature_count) {
				feature_count = parseInt(data.feature_count);
			} else if (data.wof_id && data.wof_name) {
				self.show_saved_wof_result(data.wof_id, data.wof_name);
				if (feature_count) {
					var $status = $('#collection-' + esc_str(data.collection_uuid));
					var num = $('#saved-wof li').length;
					if (num == feature_count) {
						$status.html('Finished!');
					} else {
						$status.html('Saved ' + num + ' of ' + feature_count + '...');
					}
				}
			}
		},

		has_geometry: function(geojson) {
			if (geojson.type == 'Feature') {
				return !! geojson.geometry;
			} else if (geojson.type == 'FeatureCollection') {
				var has_geometry = false;
				$.each(geojson.features, function(i, feature) {
					if (feature.geometry) {
						has_geometry = true;
					}
				});
				return has_geometry;
			} else {
				return false;
			}
		}

	};

	$(document).ready(function(){

		VenueIcon = L.Icon.extend({
			options: {
				iconUrl: mapzen.whosonfirst.boundaryissues.utils.abs_root_urlify('/images/marker-icon.png'),
				iconRetinaUrl: mapzen.whosonfirst.boundaryissues.utils.abs_root_urlify('/images/marker-icon-2x.png'),
				shadowUrl: null,
				iconAnchor: new L.Point(13, 42),
				iconSize: new L.Point(25, 42),
				popupAnchor: new L.Point(0, -42)
			}
		});

		poi_icon_base = mapzen.whosonfirst.boundaryissues.utils.abs_root_urlify('/images/categories/');

		self.setup_upload();
		self.setup_property_aliases();
	});

	return self;
})();
