var mapzen = mapzen || {};
mapzen.whosonfirst = mapzen.whosonfirst || {};
mapzen.whosonfirst.boundaryissues = mapzen.whosonfirst.boundaryissues || {};

// for the time being this assumes jQuery is present
// that decision may change some day but not today
// (20160121/thisisaaronland)

mapzen.whosonfirst.boundaryissues.branch = (function() {

	var self = {
		init: function() {
			$('#navi-branch-dropdown li a.branch').click(self.checkout_existing);
			$('#navi-branch-create').click(self.checkout_new);
		},

		checkout_existing: function(e) {
			e.preventDefault();
			var branch = $(e.target).data('branch');
			$('#navi-branch .branch-label').html(branch);
			$('#navi-branch-dropdown li a.hey-look').removeClass('hey-look');
			$(e.target).addClass('hey-look');
			self.checkout_branch(branch);
		},

		checkout_new: function(e) {
			e.preventDefault();
			if (! confirm('Checking out a new branch will incur lots of processing on the server. Are you sure?')) {
				return;
			}
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
			$('<li><a href="#" class="branch hey-look">' + branch + '</a></li>').insertBefore(divider);
			$('#navi-branch .branch-label').html(branch);
			self.checkout_branch(branch);
		},

		checkout_branch: function(branch) {
			console.log('ok: ' + branch);
		}
	};

	$(document).ready(function() {
		self.init();
	});

	return self;

})();
