JS_GITHUB = https://raw.githubusercontent.com/whosonfirst/js-mapzen-whosonfirst/master
JSON_SCHEMA_GITHUB = https://raw.githubusercontent.com/whosonfirst/whosonfirst-json-schema/master
WOF_PROPERTY_GITHUB = https://raw.githubusercontent.com/whosonfirst/whosonfirst-properties/master

htusers:
	htpasswd -c -B apache/.htusers mapzen

setup:
	ubuntu/setup-ubuntu.sh
	ubuntu/setup-git.sh
	ubuntu/setup-git-lfs.sh
	ubuntu/setup-flamework.sh
	ubuntu/setup-certified.sh
	sudo ubuntu/setup-certified-ca.sh
	sudo ubuntu/setup-certified-certs.sh
	ubuntu/setup-apache.sh
	bin/configure_secrets.sh .
	ubuntu/setup-db.sh boundaryissues boundaryissues
	ubuntu/setup-geojson-server.sh
	ubuntu/setup-pubsocketd-server.sh
	ubuntu/setup-mapshaper.sh

setup-offline:
	ubuntu/setup-redis-server.sh
	ubuntu/setup-gearmand.sh
	ubuntu/setup-logstash.sh
	ubuntu/setup-supervisor.sh

setup-pip:
	ubuntu/setup-pip-server.sh microhood neighbourhood locality

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
	git rm -f ./www/javascript/lib/*.js
	mkdir -p ./www/javascript/lib
	curl -s -o ./www/javascript/lib/mapzen.whosonfirst.brands.js $(JS_GITHUB)/src/mapzen.whosonfirst.brands.js
	curl -s -o ./www/javascript/lib/mapzen.whosonfirst.uri.js $(JS_GITHUB)/src/mapzen.whosonfirst.uri.js
	curl -s -o ./www/javascript/lib/mapzen.whosonfirst.geojson.js $(JS_GITHUB)/src/mapzen.whosonfirst.geojson.js
	curl -s -o ./www/javascript/lib/mapzen.whosonfirst.leaflet.handlers.js $(JS_GITHUB)/src/mapzen.whosonfirst.leaflet.handlers.js
	curl -s -o ./www/javascript/lib/mapzen.whosonfirst.leaflet.js $(JS_GITHUB)/src/mapzen.whosonfirst.leaflet.js
	curl -s -o ./www/javascript/lib/mapzen.whosonfirst.leaflet.styles.js $(JS_GITHUB)/src/mapzen.whosonfirst.leaflet.styles.js
	curl -s -o ./www/javascript/lib/mapzen.whosonfirst.leaflet.tangram.js $(JS_GITHUB)/src/mapzen.whosonfirst.leaflet.tangram.js
	curl -s -o ./www/javascript/lib/mapzen.whosonfirst.log.js $(JS_GITHUB)/src/mapzen.whosonfirst.log.js
	curl -s -o ./www/javascript/lib/mapzen.whosonfirst.net.js $(JS_GITHUB)/src/mapzen.whosonfirst.net.js
	curl -s -o ./www/javascript/lib/mapzen.whosonfirst.php.js $(JS_GITHUB)/src/mapzen.whosonfirst.php.js
	curl -s -o ./www/javascript/lib/mapzen.whosonfirst.placetypes.js $(JS_GITHUB)/src/mapzen.whosonfirst.placetypes.js
	curl -s -o ./www/javascript/lib/slippymap.crosshairs.js https://raw.githubusercontent.com/whosonfirst/js-slippymap-crosshairs/master/src/slippymap.crosshairs.js
	git add ./www/javascript/lib/*

update_json_schema:
	rm -rf ./schema/json/*
	curl -s -o ./schema/json/whosonfirst.schema $(JSON_SCHEMA_GITHUB)/schema/whosonfirst.schema
	curl -s -o ./schema/json/geojson.schema $(JSON_SCHEMA_GITHUB)/schema/geojson.schema
	curl -s -o ./schema/json/bbox.schema $(JSON_SCHEMA_GITHUB)/schema/bbox.schema
	curl -s -o ./schema/json/geometry.schema $(JSON_SCHEMA_GITHUB)/schema/geometry.schema

update_property_aliases:
	rm -rf ./www/meta/property_aliases.json
	curl -s -o ./www/meta/property_aliases.json $(WOF_PROPERTY_GITHUB)/aliases/property_aliases.json

update_countries_json:
	rm -rf ./www/meta/countries.json
	php bin/countries_json.php > ./www/meta/countries.json

localforage:
	curl -s -o www/javascript/localforage.js https://raw.githubusercontent.com/mozilla/localForage/master/dist/localforage.js
	curl -s -o www/javascript/localforage.min.js https://raw.githubusercontent.com/mozilla/localForage/master/dist/localforage.min.js

mapzen: styleguide tangram refill

styleguide:
	if test -e www/css/mapzen.styleguide.css; then cp www/css/mapzen.styleguide.css www/css/mapzen.styleguide.css.bak; fi
	curl -s -o www/css/mapzen.styleguide.css https://mapzen.com/common/styleguide/styles/styleguide.css
	curl -s -o www/javascript/mapzen.styleguide.min.js https://mapzen.com/common/styleguide/scripts/mapzen-styleguide.min.js
	curl -s -o www/images/selection-sprite.png https://mapzen.com/common/styleguide/images/selection-sprite.png

tangram:
	if test -e www/javascript/tangram.js; then cp www/javascript/tangram.js www/javascript/tangram.js.bak; fi
	curl -s -o www/javascript/tangram.js https://mapzen.com/tangram/tangram.debug.js
	if test -e www/javascript/tangram.min.js; then cp www/javascript/tangram.min.js www/javascript/tangram.min.js.bak; fi
	curl -s -o www/javascript/tangram.min.js https://mapzen.com/tangram/tangram.min.js

refill:
	if test -e www/tangram/refill.yaml; then cp www/tangram/refill.yaml www/tangram/refill.yaml.bak; fi
	curl -s -o www/tangram/refill.yaml https://raw.githubusercontent.com/tangrams/refill-style/gh-pages/refill-style.yaml
	perl -p -i -e "s!-(\s+)\&text_visible_poi_landuse(\s+)true!-\1&text_visible_poi_landuse\2false!" www/tangram/refill.yaml
	perl -p -i -e "s!-(\s+)\&label_visible_poi_landuse(\s+)true!-\1&label_visible_poi_landuse\2false!" www/tangram/refill.yaml
	perl -p -i -e "s!-(\s+)\&icon_visible_poi_landuse(\s+)true!-\1&icon_visible_poi_landuse\2false!" www/tangram/refill.yaml
	perl -p -i -e "s!-(\s+)\&text_visible_poi_landuse_e(\s+)true!-\1&text_visible_poi_landuse_e\2false!" www/tangram/refill.yaml
	perl -p -i -e "s!-(\s+)\&label_visible_poi_landuse_e(\s+)true!-\1&label_visible_poi_landuse_e\2false!" www/tangram/refill.yaml
	perl -p -i -e "s!-(\s+)\&icon_visible_poi_landuse_e(\s+)true!-\1&icon_visible_poi_landuse_e\2false!" www/tangram/refill.yaml

leaflet-geocoder:
	if test -e www/css/leaflet-geocoder-mapzen.css; then cp www/css/leaflet-geocoder-mapzen.css www/css/leaflet-geocoder-mapzen.css.bak; fi
	curl -s -o www/css/leaflet-geocoder-mapzen.css https://cdnjs.cloudflare.com/ajax/libs/leaflet-geocoder-mapzen/1.4.1/leaflet-geocoder-mapzen.css
	if test -e www/javascript/leaflet-geocoder-mapzen.js; then cp www/javascript/leaflet-geocoder-mapzen.js www/javascript/leaflet-geocoder-mapzen.js.bak; fi
	curl -s -o www/javascript/leaflet-geocoder-mapzen.js https://cdnjs.cloudflare.com/ajax/libs/leaflet-geocoder-mapzen/1.4.1/leaflet-geocoder-mapzen.js

pip-server:
	ubuntu/setup-golang.sh
	if test ! -d /usr/local/mapzen/go-whosonfirst-pip; then git clone git@github.com:whosonfirst/go-whosonfirst-pip.git /usr/local/mapzen/go-whosonfirst-pip; fi
	cd /usr/local/mapzen/go-whosonfirst-pip; git pull origin master; make build
	cp /usr/local/mapzen/go-whosonfirst-pip/bin/wof-pip-server services/pip-server/wof-pip-server

es-schema:
	if test -e schema/elasticsearch/mappings.boundaryissues.json; then cp schema/elasticsearch/mappings.boundaryissues.json schema/elasticsearch/mappings.boundaryissues.json.bak; fi
	curl -s -o schema/elasticsearch/mappings.boundaryissues.json https://raw.githubusercontent.com/whosonfirst/es-whosonfirst-schema/master/schema/mappings.boundaryissues.json

es-reload:
	curl -s -XDELETE 'http://$(host):9200/whosonfirst' | python -mjson.tool
	cat "schema/elasticsearch/mappings.boundaryissues.json" | curl -s -XPUT 'http://$(host):9200/whosonfirst' -d @- | python -mjson.tool

es-index:
	sudo -u www-data ./ubuntu/setup-elasticsearch-index.sh $(data)

categories:
	curl -s -o www/meta/categories.json https://raw.githubusercontent.com/whosonfirst/whosonfirst-categories/master/meta/categories.json

sources:
	curl -s -o www/meta/sources.json https://raw.githubusercontent.com/whosonfirst/whosonfirst-sources/master/data/sources-spec-latest.json
