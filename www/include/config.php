<?php
	$GLOBALS['cfg'] = array();

	# Things you may want to change in a hurry

	$GLOBALS['cfg']['site_name'] = 'Boundary Issues';
	$GLOBALS['cfg']['environment'] = 'dev';

	$GLOBALS['cfg']['site_disabled'] = 0;
	$GLOBALS['cfg']['site_disabled_retry_after'] = 0;	# seconds; if set will return HTTP Retry-After header

	# Message is displayed in the nav header in inc_head.txt

	$GLOBALS['cfg']['display_message'] = 1;
	$GLOBALS['cfg']['display_message_text'] = 'this is still a wet-paint prototype, suggestions and cluebats are welcome but please be gentle';

	# Things you'll certainly need to tweak
	# See below for details about database password(s)

	$GLOBALS['cfg']['db_main'] = array(
		'host'	=> 'localhost',
		'name'	=> 'boundaryissues',	# database name
		'user'	=> 'boundaryissues',	# database username
		'auto_connect' => 0,
	);

	$GLOBALS['cfg']['db_users'] = array(

		'host' => array(
			1 => 'localhost',
			2 => 'localhost',
		),

		'user' => 'root',

		'name' => array(
			1 => 'user1',
			2 => 'user2',
		),
	);

	# WE SHOULD STANDARDIZE ON /usr/local/data/whosonfirst-data BUT THAT IS WAITING
	# ON WORK AROUND MULTIPLE REPOSITORIES AND FUSE STUFF AND REPO LOOKUP (BELOW)
	# (20160405/thisisaaronland)

	$GLOBALS['cfg']['wof_data_dir'] = '/usr/local/mapzen/whosonfirst-data-venue-us-new-york/data/';
	$GLOBALS['cfg']['wof_pending_dir'] = '/usr/local/mapzen/whosonfirst-www-boundaryissues/pending/data/';
	$GLOBALS['cfg']['wof_pending_log_dir'] = '/usr/local/mapzen/whosonfirst-www-boundaryissues/pending/log/';

	$GLOBALS['cfg']['enable_feature_save_via_github_api'] = false;
	$GLOBALS['cfg']['wof_github_owner'] = 'whosonfirst-data';

	# ULTIMATELY THIS WILL/SHOULD BE REPLACED BY A FUNCTION THAT MAPS ANY GIVEN
	# RECORD TO A REPO BUT THAT WORK IS NOT DONE YET. ALSO THIS SHOULD PROBABLY
	# BE STORED IN `config_dev.php` UNTIL THEN (20160405/thisissaaronland)

	$GLOBALS['cfg']['wof_github_repo'] = 'whosonfirst-data-venue-us-new-york';

	$GLOBALS['cfg']['geojson_base_url'] = 'http://localhost:8181';
	$GLOBALS['cfg']['dbug_log'] = '/var/log/boundaryissues_dbug.log';
	$GLOBALS['cfg']['gearman_log'] = '/var/log/boundaryissues_gearman.log';
	$GLOBALS['cfg']['gearman_client_timeout'] = 3000;

	// Elasticsearch defaults
	$GLOBALS['cfg']['elasticsearch_host'] = 'localhost';
	$GLOBALS['cfg']['elasticsearch_port'] = '9200';
	$GLOBALS['cfg']['elasticsearch_http_timeout'] = 10;

	// WOF-specific ES settings
	$GLOBALS['cfg']['wof_elasticsearch_index'] = 'whosonfirst';
	$GLOBALS['cfg']['wof_elasticsearch_host'] = 'localhost';
	$GLOBALS['cfg']['wof_elasticsearch_port'] = '9200';

	// Offline Tasks-specific ES settings
	$GLOBALS['cfg']['offline_tasks_elasticsearch_index'] = 'offline_tasks';
	$GLOBALS['cfg']['offline_tasks_elasticsearch_host'] = 'localhost';
	$GLOBALS['cfg']['offline_tasks_elasticsearch_port'] = '9200';

	$GLOBALS['cfg']['gearman_host'] = 'localhost';
	$GLOBALS['cfg']['gearman_port'] = '4730';

	# hard coding this URL will ensure it works in cron mode too

	$GLOBALS['cfg']['server_scheme'] = (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] == 'on')) ? 'https' : 'http';
	$GLOBALS['cfg']['server_name'] = isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'fake.com';

	$GLOBALS['cfg']['abs_root_url']		= "{$GLOBALS['cfg']['server_scheme']}://{$GLOBALS['cfg']['server_name']}/";
	$GLOBALS['cfg']['safe_abs_root_url']	= $GLOBALS['cfg']['abs_root_url'];

	# ecause the nginx configs are still busted... (20160429/thisisaaronland)
	# $GLOBALS['cfg']['data_abs_root_url'] = "https://whosonfirst.mapzen.com/data/";
	$GLOBALS['cfg']['data_abs_root_url'] = "https://s3.amazonaws.com/whosonfirst.mapzen.com/data/";

	# Hard-coding these paths will save some stat() ops

	$GLOBALS['cfg']['smarty_template_dir'] = realpath(dirname(__FILE__) . '/../templates/');
	$GLOBALS['cfg']['smarty_compile_dir'] = realpath(dirname(__FILE__) . '/../templates_c/');

	# These should be left as-is, unless you have an existing password database not using bcrypt and
	# you need to do auto-promotion on login.

	$GLOBALS['cfg']['passwords_use_bcrypt'] = true;
	$GLOBALS['cfg']['passwords_allow_promotion'] = false;

	# Things you may need to tweak

	# Auth roles

	$GLOBALS['cfg']['enable_feature_auth_roles_autopromote_staff'] = 0;
	$GLOBALS['cfg']['enable_feature_auth_roles_autopromote_staff_dev'] = 0;
	$GLOBALS['cfg']['enable_feature_auth_roles_autopromote_staff_shell'] = 0;

	# Caching stuff

	$GLOBALS['cfg']['enable_feature_cache_prefixes'] = 1;
	$GLOBALS['cfg']['cache_prefix'] = $GLOBALS['cfg']['environment'];

	# Note: memcache stuff is not enabled by default but is
	# available in the 'extras' directory

	$GLOBALS['cfg']['auth_cookie_domain'] = parse_url($GLOBALS['cfg']['abs_root_url'], 1);
	$GLOBALS['cfg']['auth_cookie_name'] = 'boundaryissues';
	$GLOBALS['cfg']['auth_cookie_require_https'] = 0;

	$GLOBALS['cfg']['crumb_ttl_default'] = 300;	# seconds

	$GLOBALS['cfg']['rewrite_static_urls'] = array(
		# '/foo' => '/bar/',
	);

	$GLOBALS['cfg']['email_from_name']	= 'flamework app';
	$GLOBALS['cfg']['email_from_email']	= 'admin@ourapp.com';
	$GLOBALS['cfg']['auto_email_args']	= '-fadmin@ourapp.com';

	# Things you can probably not worry about

	$GLOBALS['cfg']['user'] = null;

	# If you are running Flamework on a host where you can not change the permissions
	# on the www/templates_c directory (to be owned by the web server) you'll need to
	# do a couple of things. The first is to set the 'smarty_compile' flag to 0. That
	# means you'll need to pre-compile all your templates by hand. You can do this with
	# 'compile-templates.php' script that is part of Flamework 'bin' directory. Obviously
	# this doesn't make much sense if you are actively developing a site but might be
	# useful if you've got something working and then just want to run it on a shared
	# hosting provider where you can't change the permissions on on files, like pair or
	# dreamhost. (20120110/straup)

	$GLOBALS['cfg']['smarty_compile'] = 1;

	# Do not always compile all the things all the time. Unless you know you're in to
	# that kind of thing. One important thing to note about this setting is that you
	# will need to reenabled it at least once (and load the template in question) if
	# you've got a template that calls a non-standard function. For example, something
	# like: {$foo|@bar_all_the_things}

	$GLOBALS['cfg']['smarty_force_compile'] = 0;

	$GLOBALS['cfg']['http_timeout'] = 3;

	$GLOBALS['cfg']['check_notices'] = 1;

	$GLOBALS['cfg']['db_profiling'] = 0;


	# db_enable_poormans_*
	#
	# If enabled, then the relevant database configs and handles
	# will be automagically prepopulated using the relevant information
	# in 'db_main'. Again, see below for database passwords

	# You should enable/set these flags if you want to
	# use flamework in a setting where you only have access
	# to a single database.

	$GLOBALS['cfg']['db_enable_poormans_federation'] = 1;
	$GLOBALS['cfg']['db_enable_poormans_slaves'] = 0;
	$GLOBALS['cfg']['db_poormans_slaves_user'] = '';

	# For when you want to use tickets but can't tweak
	# your my.cnf file or set up a dedicated ticketing
	# server. flamework does not use tickets as part of
	# core (yet) so this is really only necessary if your
	# application needs db tickets.

	$GLOBALS['cfg']['db_enable_poormans_ticketing'] = 1;

	# This will assign $pagination automatically for Smarty but
	# you probably don't want to do this for anything resembling
	# a complex application...

	$GLOBALS['cfg']['pagination_assign_smarty_variable'] = 0;

	$GLOBALS['cfg']['pagination_per_page'] = 36;
	$GLOBALS['cfg']['pagination_spill'] = 2;
	$GLOBALS['cfg']['pagination_style'] = 'pretty';

	$GLOBALS['cfg']['pagination_keyboard_shortcuts'] = 1;
	$GLOBALS['cfg']['pagination_touch_shortcuts'] = 1;

	# Feature flags

	$GLOBALS['cfg']['enable_feature_signup'] = 1;
	$GLOBALS['cfg']['enable_feature_signin'] = 1;
	$GLOBALS['cfg']['enable_feature_persistent_login'] = 1;
	$GLOBALS['cfg']['enable_feature_account_delete'] = 1;
	$GLOBALS['cfg']['enable_feature_password_retrieval'] = 1;
	$GLOBALS['cfg']['enable_feature_artisanal_integers'] = 1;

	# Enable this flag to show a full call chain (instead of just the
	# immediate caller) in database query log messages and embedded in
	# the actual SQL sent to the server.

	$GLOBALS['cfg']['db_full_callstack'] = 0;
	$GLOBALS['cfg']['allow_prefetch'] = 0;

	# Load these libraries on every page

	$GLOBALS['cfg']['autoload_libs'] = array(
		'cache',
		'dbug',
		'wof_smarty',
		'wof_render',
		'logstash',
		'redis',
	);

	# THINGS YOU SHOULD DEFINE IN YOUR secrets.php FILE WHICH IS NOT
	# MEANT TO BE CHECKED IN EVER. DON'T DO IT. AND DON'T DEFINE THESE
	# THINGS HERE. REALLY.

	# $GLOBALS['cfg']['crypto_cookie_secret'] = '';
	# $GLOBALS['cfg']['crypto_password_secret'] = '';
	# $GLOBALS['cfg']['crypto_crumb_secret'] = '';

	# $GLOBALS['cfg']['db_main']['pass'] = '';
	# $GLOBALS['cfg']['db_users']['pass'] = '';
	# $GLOBALS['cfg']['db_poormans_slaves_pass'] = 'READ-FROM-SECRETS';

	# the end
	# API methods and "blessings" are defined at the bottom

	# API feature flags

	$GLOBALS['cfg']['enable_feature_api'] = 1;

	$GLOBALS['cfg']['enable_feature_api_documentation'] = 1;
	$GLOBALS['cfg']['enable_feature_api_explorer'] = 1;
	$GLOBALS['cfg']['enable_feature_api_logging'] = 1;
	$GLOBALS['cfg']['enable_feature_api_throttling'] = 0;

	$GLOBALS['cfg']['enable_feature_api_require_keys'] = 0;		# because oauth2...
	$GLOBALS['cfg']['enable_feature_api_register_keys'] = 1;

	$GLOBALS['cfg']['enable_feature_api_delegated_auth'] = 1;
	$GLOBALS['cfg']['enable_feature_api_authenticate_self'] = 1;

	# API URLs and endpoints

	$GLOBALS['cfg']['api_abs_root_url'] = '';	# leave blank - set in api_config_init()
	$GLOBALS['cfg']['site_abs_root_url'] = '';	# leave blank - set in api_config_init()

	$GLOBALS['cfg']['api_subdomain'] = '';
	$GLOBALS['cfg']['api_endpoint'] = 'api/rest/';

	$GLOBALS['cfg']['api_require_ssl'] = 1;

	$GLOBALS['cfg']['api_auth_type'] = 'oauth2';
	$GLOBALS['cfg']['api_oauth2_require_authentication_header'] = 0;
	$GLOBALS['cfg']['api_oauth2_allow_get_parameters'] = 1;

	# API site keys (TTL is measured in seconds)

	$GLOBALS['cfg']['enable_feature_api_site_keys'] = 1;
	$GLOBALS['cfg']['enable_feature_api_site_tokens'] = 1;

	$GLOBALS['cfg']['api_site_keys_ttl'] = 28800;		# 8 hours
	$GLOBALS['cfg']['api_site_tokens_ttl'] = 28000;		# 8 hours
	$GLOBALS['cfg']['api_site_tokens_user_ttl'] = 3600;	# 1 hour

	$GLOBALS['cfg']['api_explorer_keys_ttl'] = 28800;		# 8 hours
	$GLOBALS['cfg']['api_explorer_tokens_ttl'] = 28000;		# 8 hours
	$GLOBALS['cfg']['api_explorer_tokens_user_ttl'] = 28000;	# 8 hours

	# We test this in lib_api_auth_oauth2.php to see whether or
	# not we need to throw an error - it's possible that we want
	# this to be computed in lib_api_config for example but right
	# now we're explicit about everything (20141114/straup)

	$GLOBALS['cfg']['enable_feature_api_oauth2_tokens_null_users'] = 1;

	# As in API key roles - see also: api_keys_roles_map()
	# (20141114/straup)

	$GLOBALS['cfg']['api_oauth2_tokens_null_users_allowed_roles'] = array(
		'site',
		'api_explorer',
		'infrastructure',
	);

	$GLOBALS['cfg']['enable_feature_api_cors'] = 1;
	$GLOBALS['cfg']['api_cors_allow_origin'] = '*';

	# API pagination

	$GLOBALS['cfg']['api_per_page_default'] = 100;
	$GLOBALS['cfg']['api_per_page_max'] = 500;

	# The actual API config

	$GLOBALS['cfg']['api'] = array(

		'formats' => array( 'json' ),
		'default_format' => 'json',

		# We're defining methods using the method_definitions
		# hooks defined below to minimize the clutter in the
		# main config file, aka this one (20130308/straup)
		'methods' => array(),

		# We are NOT doing the same for blessed API keys since
		# it's expected that their number will be small and
		# manageable (20130308/straup)

		'blessings' => array(
			'xxx-apikey' => array(
				'hosts' => array('127.0.0.1'),
				# 'tokens' => array(),
				# 'environments' => array(),
				'methods' => array(
					'foo.bar.baz' => array(
						'environments' => array('sd-931')
					)
				),
				'method_classes' => array(
					'foo.bar' => array(
						# see above
					)
				),
			),
		),
	);

	# Load api methods defined in separate PHP files whose naming
	# convention is FLAMEWORK_INCLUDE_DIR . "/config_api_{$definition}.php";
	#
	# IMPORTANT: This is syntactic sugar and helper code to keep the growing
	# number of API methods out of the main config. Stuff is loaded in to
	# memory in lib_api_config:api_config_init (20130308/straup)

	$GLOBALS['cfg']['api_method_definitions'] = array(
		'methods',
	);

	# START OF flamework-github-sso stuff

	$GLOBALS['cfg']['github_oauth_key'] = 'READ-FROM-SECRETS';
	$GLOBALS['cfg']['github_oauth_secret'] = 'READ-FROM-SECRETS';
	$GLOBALS['cfg']['github_api_scope'] = 'user:email,repo';
	$GLOBALS['cfg']['github_oauth_callback'] = 'auth/';

	# END OF flamework-github-sso stuff

	# START OF flamework-redis stuff

	$GLOBALS['cfg']['redis_scheme'] = 'tcp';
	$GLOBALS['cfg']['redis_host'] = 'localhost';
	$GLOBALS['cfg']['redis_port'] = 6379;

	# END OF flamework-redis stuff

	# START OF flamework-logstash stuff

	$GLOBALS['cfg']['logstash_redis_channel'] = 'flogstash';
	$GLOBALS['cfg']['logstash_redis_host'] = 'localhost';
	$GLOBALS['cfg']['logstash_redis_port'] = 6379;

	# END OF flamework-logstash stuff

	# START OF wof-geojson-server stuff
	# see also: services/geojson-server

	$GLOBALS['cfg']['wof_geojson_server_scheme'] = 'http';
	$GLOBALS['cfg']['wof_geojson_server_host'] = 'localhost';
	$GLOBALS['cfg']['wof_geojson_server_port'] = 8181;

	# END OF wof-geojson-server stuff
