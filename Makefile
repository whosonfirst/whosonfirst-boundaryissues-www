TIMESTAMP = $(shell date +%Y%m%d%H%M%S)
JS_GITHUB = https://raw.githubusercontent.com/whosonfirst/js-mapzen-whosonfirst/master
JSON_SCHEMA_GITHUB = https://raw.githubusercontent.com/whosonfirst/whosonfirst-json-schema/master

setup:
	ubuntu/setup-ubuntu.sh
	ubuntu/setup-flamework.sh
	ubuntu/setup-certified.sh
	sudo ubuntu/setup-certified-ca.sh
	sudo ubuntu/setup-certified-certs.sh
	ubuntu/setup-apache.sh
	bin/configure_secrets.php .
	ubuntu/setup-db.sh boundaryissues boundaryissues

templates:
	php -q ./bin/compile-templates.php

secret:
	php -q ./bin/generate_secret.php

test:
	prove -v --exec 'php --php-ini ./tests/php.ini' ./tests/*.t

cover:
	rm -f ./tests/coverage.state
	rm -rf ./coverage
	-make test
	php -q ./tests/coverage.php

update_js:
	rm -rf ./www/javascript/lib/*
	curl -s -o ./www/javascript/lib/mapzen.whosonfirst.data-$(TIMESTAMP).js $(JS_GITHUB)/src/mapzen.whosonfirst.data.js
	curl -s -o ./www/javascript/lib/mapzen.whosonfirst.geojson-$(TIMESTAMP).js $(JS_GITHUB)/src/mapzen.whosonfirst.geojson.js
	curl -s -o ./www/javascript/lib/mapzen.whosonfirst.leaflet.handlers-$(TIMESTAMP).js $(JS_GITHUB)/src/mapzen.whosonfirst.leaflet.handlers.js
	curl -s -o ./www/javascript/lib/mapzen.whosonfirst.leaflet-$(TIMESTAMP).js $(JS_GITHUB)/src/mapzen.whosonfirst.leaflet.js
	curl -s -o ./www/javascript/lib/mapzen.whosonfirst.leaflet.styles-$(TIMESTAMP).js $(JS_GITHUB)/src/mapzen.whosonfirst.leaflet.styles.js
	curl -s -o ./www/javascript/lib/mapzen.whosonfirst.leaflet.tangram-$(TIMESTAMP).js $(JS_GITHUB)/src/mapzen.whosonfirst.leaflet.tangram.js
	curl -s -o ./www/javascript/lib/mapzen.whosonfirst.log-$(TIMESTAMP).js $(JS_GITHUB)/src/mapzen.whosonfirst.log.js
	curl -s -o ./www/javascript/lib/mapzen.whosonfirst.net-$(TIMESTAMP).js $(JS_GITHUB)/src/mapzen.whosonfirst.net.js
	curl -s -o ./www/javascript/lib/mapzen.whosonfirst.php-$(TIMESTAMP).js $(JS_GITHUB)/src/mapzen.whosonfirst.php.js
	curl -s -o ./www/javascript/lib/mapzen.whosonfirst.placetypes-$(TIMESTAMP).js $(JS_GITHUB)/src/mapzen.whosonfirst.placetypes.js

update_json_schema:
	rm -rf ./schema/json/*
	curl -s -o ./schema/json/whosonfirst.schema $(JSON_SCHEMA_GITHUB)/schema/whosonfirst.schema
	curl -s -o ./schema/json/geojson.schema $(JSON_SCHEMA_GITHUB)/schema/geojson.schema
	curl -s -o ./schema/json/bbox.schema $(JSON_SCHEMA_GITHUB)/schema/bbox.schema
	curl -s -o ./schema/json/geometry.schema $(JSON_SCHEMA_GITHUB)/schema/geometry.schema

styleguide:
	if test -e www/css/mapzen.styleguide.css; then cp www/css/mapzen.styleguide.css www/css/mapzen.styleguide.css.bak; fi
	curl -s -o www/css/mapzen.styleguide.css https://mapzen.com/common/styleguide/styles/styleguide.css
	curl -s -o www/javascript/mapzen.styleguide.min.js https://mapzen.com/common/styleguide/scripts/mapzen-styleguide.min.js 

tangram:
	if test -e www/javascript/tangram.js; then cp www/javascript/tangram.js www/javascript/tangram.js.bak; fi
	curl -s -o www/javascript/tangram.js https://mapzen.com/tangram/tangram.debug.js
	if test -e www/javascript/tangram.min.js; then cp www/javascript/tangram.min.js www/javascript/tangram.min.js.bak; fi
	curl -s -o www/javascript/tangram.min.js https://mapzen.com/tangram/tangram.min.js

refill:
	if test -e www/tangram/refill.yaml; then cp www/tangram/refill.yaml www/tangram/refill.yaml.bak; fi
	curl -s -o www/tangram/refill.yaml https://raw.githubusercontent.com/tangrams/refill-style/gh-pages/refill-style.yaml

pip-server:
	ubuntu/setup-golang.sh
	if test ! -d /usr/local/mapzen/go-whosonfirst-pip; then git clone git@github.com:whosonfirst/go-whosonfirst-pip.git /usr/local/mapzen/go-whosonfirst-pip; fi
	cd /usr/local/mapzen/go-whosonfirst-pip; git pull origin master; make build
	cp /usr/local/mapzen/go-whosonfirst-pip/bin/wof-pip-server services/pip-server/wof-pip-server
