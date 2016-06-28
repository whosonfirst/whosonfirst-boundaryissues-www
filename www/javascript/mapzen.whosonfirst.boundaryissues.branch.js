var mapzen = mapzen || {};
mapzen.whosonfirst = mapzen.whosonfirst || {};
mapzen.whosonfirst.boundaryissues = mapzen.whosonfirst.boundaryissues || {};

// for the time being this assumes jQuery is present
// that decision may change some day but not today
// (20160121/thisisaaronland)

mapzen.whosonfirst.boundaryissues.branch = (function() {

	var self = {
		init: function() {
			$('#navi-branch-dropdown').click(self.checkout_existing);
			$('#navi-branch-create').click(self.checkout_new);
			$('#toggle-git-branch a').click(function(e) {
				e.preventDefault();
				if ($(document.body).hasClass('show-git-branch')) {
					$(document.body).removeClass('show-git-branch');
					$('#toggle-git-branch a').html('Show git branch');
					var show = 0;
				} else {
					$(document.body).addClass('show-git-branch');
					$('#toggle-git-branch a').html('Hide git branch');
					var show = 1;
				}
				var onsuccess = function(rsp) {
					mapzen.whosonfirst.log.info('set show_git_branch to ' + show);
				};
				var onerror = function(rsp) {
					mapzen.whosonfirst.log.info('could not set show_git_branch');
				};
				mapzen.whosonfirst.boundaryissues.api.api_call('wof.users_settings_set', {
					name: 'show_git_branch',
					value: show
				}, onsuccess, onerror);
			});

		},

		checkout_existing: function(e) {
			if (e.target.nodeName != 'A' ||
			    ! $(e.target).hasClass('branch')) {
				return;
			}
			e.preventDefault();
			var branch = $(e.target).data('branch');
			$('#navi-branch .branch-label').html(branch);
			$('#navi-branch-dropdown li a.hey-look').removeClass('hey-look');
			$(e.target).addClass('hey-look');
			self.checkout_branch(branch);
		},

		checkout_new: function(e) {
			e.preventDefault();
			var branch = prompt('What should we call your new branch?');
			if (typeof branch != 'string') {
				return;
			}
			if (! branch.match(/^[a-z0-9-_]+$/i)) {
				alert('Sorry, you can only use alphanumerics, dashes, or underbars.');
				return;
			}
			$('#navi-branch-dropdown li a.hey-look').removeClass('hey-look');
			var divider = $('#navi-branch-dropdown li.divider').get(1);
			$('<li><a href="#" class="branch hey-look" data-branch="' + branch + '">' + branch + '</a></li>').insertBefore(divider);
			$('#navi-branch .branch-label').html(branch);
			self.checkout_branch(branch);
		},

		checkout_branch: function(branch) {
			var onsuccess = function(rsp) {
				mapzen.whosonfirst.log.info('checked out ' + rsp.branch);
			};
			var onerror = function(rsp) {
				alert('There was a problem checking out ' + branch + '.');
				mapzen.whosonfirst.log.info('could not check out ' + branch);
			};
			mapzen.whosonfirst.boundaryissues.api.api_call('wof.checkout_branch', {
				branch: branch
			}, onsuccess, onerror);
		}
	};

	$(document).ready(function() {
		self.init();
	});

	return self;

})();
