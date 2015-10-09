#!/bin/sh

sudo -u postgres createdb whosonfirst
sudo -u postgres psql -d whosonfirst -c "CREATE EXTENSION postgis;"
sudo -u postgres psql -d whosonfirst -c "CREATE EXTENSION postgis_topology;"
sudo -u postgres psql -d whosonfirst -c "CREATE TABLE whosonfirst (id BIGINT PRIMARY KEY, parent_id BIGINT, placetype VARCHAR, properties TEXT, geom GEOGRAPHY(MULTIPOLYGON, 4326), centroid GEOGRAPHY(POINT, 4326));"
sudo -u postgres psql -d whosonfirst -c "CREATE INDEX by_geom ON whosonfirst USING GIST(geom);"
sudo -u postgres psql -d whosonfirst -c "CREATE INDEX by_placetype ON whosonfirst (placetype);"
sudo -u postgres psql -d whosonfirst -c "VACUUM ANALYZE;"
