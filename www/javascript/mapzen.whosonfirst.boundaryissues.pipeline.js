var mapzen = mapzen || {};
mapzen.whosonfirst = mapzen.whosonfirst || {};
mapzen.whosonfirst.boundaryissues = mapzen.whosonfirst.boundaryissues || {};

// for the time being this assumes jQuery is present
// that decision may change some day but not today
// (20160121/thisisaaronland)

mapzen.whosonfirst.boundaryissues.pipeline = (function(){

	var $result;

	var self = {

		setup_pipeline: function() {
			var html = self.pipeline_controls();
			$('#pipeline-form #upload-preview-props').html(html);
			$('#pipeline-type').change(function() {
				var type = $('#pipeline-type').val();
				self.pipeline_options(type);
				$('#btn-create').removeClass('hidden');
			});

			$('#pipeline-form').submit(function(e) {
				e.preventDefault();

				var api_method = 'wof.pipeline.create';

				var data = new FormData();
				data.append('crumb', $('#pipeline-form').data('crumb'));
				data.append('type', $('#pipeline-type').val());
				data.append('meta_json', self.get_pipeline_meta_json());

				var onsuccess = function(rsp) {
					if (rsp.pipeline_id) {
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

				mapzen.whosonfirst.boundaryissues.api.api_call(api_method, data, onsuccess, onerror);

				// Show some user feedback
				$result.html('<div class="alert alert-info">Uploading...</div>');
			});
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

		pipeline_controls: function() {

			var types = [
				'meta_files',
				'merge_pr',
				'neighbourhood',
				'venues'
				//'remove_properties',
				//'fix_property_type',
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
				var venues_parent = meta.venues_parent || '';

				html = '<p>Your zip file should include a selection of GeoJSON FeatureCollection files.</p>';
				html += '<div class="input-group">';
				html += '<input type="checkbox" id="process_venues"' + process_venues_checked + '>';
				html += '<label for="process_venues">Process descendant venues</label>';
				html += '</div>';

				html += '<div id="venues-parent-group" class="input-group hidden">';
				html += '<label for="venues-parent">Venues parent WOF ID</label>';
				html += '<input type="text" id="venues-parent" value="' + htmlspecialchars(venues_parent) + '">';
				html += '</div>';

			} else if (type == 'venues') {

				var venues_parent = meta.venues_parent || '';

				html += '<div id="venues-parent-group" class="input-group">';
				html += '<label for="venues-parent">Venues parent WOF ID</label>';
				html += '<input type="text" id="venues-parent" value="' + htmlspecialchars(venues_parent) + '">';
				html += '</div>';

			} else if (type == 'remove_properties') {

				var property_list = meta.property_list || '';

				html = '<p>Your zip file should include a CSV file called <code>remove_properties.csv</code>.</p>';
				html += '<div class="input-group">';
				html += '<label for="property_list">Properties to remove (comma-separated)</label>';
				html += '<input type="text" id="property_list" value="' + htmlspecialchars(property_list) + '">';
				html += '</div>';
			} else if (type == 'fix_property_type') {

				var repo = meta.repo || 'whosonfirst-data';
				var property = meta.property || '';
				var property_type = meta.property_type || '';

				html += '<div class="input-group">';
				html += '<label for="repo">Repository</label>';
				html += '<input type="text" id="repo" value="' + htmlspecialchars(repo) + '">';
				html += '</div>';

				html += '<div class="input-group">';
				html += '<label for="property">Property to check</label>';
				html += '<input type="text" id="property" value="' + htmlspecialchars(property) + '">';
				html += '</div>';

				html += '<div class="input-group">';
				html += '<label for="property">Property type</label>';
				html += '<input type="text" id="property_type" value="' + htmlspecialchars(property_type) + '">';
				html += '</div>';
			} else if (type == 'merge_pr') {

				var repo = meta.repo || 'whosonfirst-data';
				var pr_number = meta.pr_number || '';

				html += '<div class="input-group">';
				html += '<label for="repo">Repository</label>';
				html += '<input type="text" id="repo" value="' + htmlspecialchars(repo) + '">';
				html += '</div>';

				html += '<div class="input-group">';
				html += '<label for="property">Pull request number</label>';
				html += '<input type="text" id="pr_number" value="' + htmlspecialchars(pr_number) + '">';
				html += '</div>';
			} else {
				$('#pipeline-options').html('Unknown pipeline type: ' + type);
				return false;
			}

			var default_slack_handle = $('#upload-form, #pipeline-form').data('slack-handle');
			var slack_handle = meta.slack_handle || default_slack_handle || '';
			var meta_files_checked = meta.generate_meta_files ? ' checked="checked"' : '';

			html += '<div class="input-group">';
			html += '<label for="slack_handle">Ping my Slack handle when finished</label>';
			html += '<input type="text" id="slack_handle" value="' + htmlspecialchars(slack_handle) + '">';
			html += '</div>';

			$('#pipeline-options').html(html);

			$('#process_venues').change(function(e) {
				if (e.target.checked) {
					$('#venues-parent-group').removeClass('hidden');
				} else {
					$('#venues-parent-group').addClass('hidden');
				}
			});

			mapzen.whosonfirst.boundaryissues.upload.upload_is_ready = true;
			$('#upload-btn').addClass('btn-primary');
			$('#upload-btn').attr('disabled', false);
		},

		get_pipeline_meta_json: function() {
			var meta = self.meta || {
				slack_handle: ''
			};

			meta.type = $('#pipeline-type').val();
			meta.slack_handle = $('#slack_handle').val();

			if (meta.type == 'meta_files') {
				meta.repo = $('#repo').val();
			} else if (meta.type == 'neighbourhood') {
				meta.process_venues = $('#process_venues')[0].checked;
				if (meta.process_venues) {
					meta.venues_parent = $('#venues-parent').val();
				}
			} else if (meta.type == 'venues') {
				meta.venues_parent = $('#venues-parent').val();
			} else if (meta.type == 'remove_properties') {
				meta.property_list = $('#property_list').val();
			} else if (meta.type == 'fix_property_type') {
				meta.repo = $('#repo').val();
				meta.property = $('#property').val();
				meta.property_type = $('#property_type').val();
			} else if (meta.type == 'merge_pr') {
				meta.repo = $('#repo').val();
				meta.pr_number = parseInt($('#pr_number').val());
			}

			return JSON.stringify(meta);
		},

		show_result: function(rsp) {
			if (rsp.error && rsp.error.message) {
				$result.html('<div class="alert alert-danger">Error: ' + rsp.error.message + '</div>');
				mapzen.whosonfirst.log.error(rsp.error.message);
			} else if (rsp.error && typeof rsp.error == 'string') {
				$result.html('<div class="alert alert-danger">Error: ' + rsp.error + '</div>');
				mapzen.whosonfirst.log.error(rsp.error);
			} else if (rsp.errors) {
				$result.html('<div class="alert alert-danger">Errors:<ul><li>' + rsp.errors.join('</li><li>') + '</li></ul></div>');
				mapzen.whosonfirst.log.error(rsp.errors.join(', '));
			} else {
				$result.html('<div class="alert alert-danger">Oh noes, an error! Check the JavaScript console?</div>');
				mapzen.whosonfirst.log.error(rsp);
			}
		}
	};

	$(document).ready(function(){
		$result = $('#upload-result');
		self.setup_pipeline();
	});

	return self;
})();
