TIMESTAMP = $(shell date +%Y%m%d%H%M%S)
JS_GITHUB = https://raw.githubusercontent.com/whosonfirst/js-mapzen-whosonfirst/master

all: clean todo

clean:
	rm -f ./TODO.txt

todo: TODO.txt

TODO.txt:
	@echo "Generating TODO.txt file"
	@echo "# This file was generated automatically by grep-ing for 'TO DO' in the source code." > ./TODO.txt
	@echo "# This file is meant as a pointer to the actual details in the files themselves." >> TODO.txt
	@echo "# This file was created "`date` >> TODO.txt
	@echo "" >> TODO.txt
	@-grep -n -r -e "TO DO" www >> TODO.txt
	@-grep -n -r -e "TO DO" bin >> TODO.txt
	@-grep -n -r -e "TODO" www >> TODO.txt
	@-grep -n -r -e "TODO" bin >> TODO.txt

setup:
	./ubuntu/setup.sh
	./ubuntu/setup-ubuntu.sh
	./ubuntu/setup-apache.sh
	./bin/configure_secrets.php .
	./ubuntu/setup-db.sh boundaryissues boundaryissues

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
