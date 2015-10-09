#!/bin/sh

sudo su -m postgres
createdb whosonfirst
psql -d whosonfirst -c "CREATE EXTENSION postgis;"
psql -d whosonfirst -c "CREATE EXTENSION postgis_topology;"
psql -d whosonfirst -c "CREATE TABLE whosonfirst (id BIGINT PRIMARY KEY, parent_id BIGINT, placetype VARCHAR, properties TEXT, geom GEOGRAPHY(MULTIPOLYGON, 4326), centroid GEOGRAPHY(POINT, 4326));"
psql -d whosonfirst -c "CREATE INDEX by_geom ON whosonfirst USING GIST(geom);"
psql -d whosonfirst -c "CREATE INDEX by_placetype ON whosonfirst (placetype);"
psql -d whosonfirst -c "VACUUM ANALYZE;"
