#!/usr/bin/env python

import StringIO
import sys
import logging
import os
import re
import flask
from flask import Flask, request, jsonify

import geojson
import requests
import json
import mapzen.whosonfirst.geojson
import mapzen.whosonfirst.export
import mapzen.whosonfirst.search
import mapzen.whosonfirst.utils
import mapzen.whosonfirst.placetypes
import mapzen.whosonfirst.hierarchy
import mapzen.whosonfirst.spatial.whosonfirst

app = Flask(__name__)
app.config['MAX_CONTENT_LENGTH'] = 116 * 1024 * 1024

@app.before_request
def init():
	flask.g.wof_pending_dir = os.environ.get('WOF_PENDING_DIR', '/usr/local/data/whosonfirst-pending/')
	flask.g.geojson_encoder = mapzen.whosonfirst.geojson.encoder(precision=None)

	flask.g.mapzen_api_key = os.environ.get('MAPZEN_API_KEY', '')
	flask.g.api_client = mapzen.whosonfirst.spatial.whosonfirst.api(api_key=flask.g.mapzen_api_key)
	flask.g.hierarchy_ancs = mapzen.whosonfirst.hierarchy.ancestors(spatial_client=flask.g.api_client)

@app.route('/encode', methods=['POST'])
def geojson_encode():

	try:
		g = request.form['geojson']
		f = geojson.loads(g)
	except Exception, e:
		error = "failed to load geojson, because %s" % e
		logging.error(error)
		return jsonify(ok=0, error=error)

	if (f.is_valid == False):
		error = "GeoJSON doesn't validate: %s" % f.errors()
		logging.error(error)
		return jsonify(ok=0, error=error)

	e = flask.g.geojson_encoder

	try:
		fh = StringIO.StringIO()
		e.encode_feature(f, fh)
	except Exception, e:
		error = "failed to encode geojson, because %s" % e
		logging.error(error)
		return jsonify(ok=0, error=error)

	fh.seek(0)
	encoded = fh.read()

	return jsonify(ok=1, encoded=encoded)

@app.route('/save', methods=['POST'])
def geojson_save():

	try:
		g = request.form['geojson']
		branch = request.form['branch']
		f = geojson.loads(g)
	except Exception, e:
		err = "failed to load geojson, because %s" % e
		return jsonify(ok=0, error=err)

	p = re.compile('^[a-zA-Z0-9-_]+$')
	if not p.match(branch):
		return jsonify(ok=0, error='Invalid branch name: %s' % branch)

	if (f.is_valid == False):
		error = "GeoJSON doesn't validate: %s" % f.errors()
		logging.error(error)
		return jsonify(ok=0, error=error)

	try:
		data_dir = "%s%s/data" % (flask.g.wof_pending_dir, branch)
		if not os.path.exists(data_dir):
			os.makedirs(data_dir, 0775)
		ff = mapzen.whosonfirst.export.flatfile(data_dir, debug=False)
		path = ff.export_feature(f)
	except Exception, e:
		error = "failed to export geojson, because %s" % e
		logging.error(error)
		return jsonify(ok=0, error=error)

	if not path:
		return jsonify(ok=0, error="File export returned an empty path, maybe check file permissions?")

	# Repeat back the file we just wrote
	try:
		gf = open(path, 'r')
		exported = gf.read()
	except Exception, e:
		error = "failed to read exported geojson, because %s" % e
		logging.error(error)
		return jsonify(ok=0, error=error)

	return jsonify(ok=1, geojson=exported)

@app.route('/pip', methods=['POST'])
def geojson_pip():
	try:
		g = request.form['geojson']
		feature = geojson.loads(g)
	except Exception, e:
		err = "failed to load geojson, because %s" % e
		return jsonify(ok=0, error=err)

	props = feature["properties"]
	geom = feature["geometry"]
	placetype = props["wof:placetype"]
	lat = geom["coordinates"][1]
	lng = geom["coordinates"][0]

	if (mapzen.whosonfirst.placetypes.is_valid_placetype(placetype) == False):
		return jsonify(ok=0, error="What is that placetype?")

	if not "wof:id" in props:
		props["wof:id"] = -1

	try:
		flask.g.hierarchy_ancs.rebuild_feature(feature)
	except Exception, e:
		error = "failed to determine hierarchy, because %s" % e
		logging.error(error)
		return jsonify(ok=0, error=error)

	hierarchy = feature["properties"]["wof:hierarchy"]
	parent_id = feature["properties"]["wof:parent_id"]

	for h in hierarchy:
		if "venue_id" in h and h["venue_id"] == -1:
			del h["venue_id"]

	pt = mapzen.whosonfirst.placetypes.placetype(placetype)
	parents = []

	for p in pt.parents():

		# These won't pip correctly, so just skip em
		if str(p) in ("building", "address"):
			continue

		filters = {
			"wof:placetype_id": p.id()
		}

		kwargs = {
			"filters": filters,
			"extras": "wof:superseded_by,edtf:deprecated"
		}

		for r in flask.g.api_client.point_in_polygon(lat, lng, **kwargs):

			# see this - it's not a feature... it should be a thing you
			# specify in filters and have the spatial client take care
			# of for you but it's currently blocked on this:
			# https://github.com/whosonfirst/go-whosonfirst-pip/issues/32

			if r['edtf:deprecated'] != "" and r['edtf:deprecated'] != 'uuuu':
				continue

			if len(r['wof:superseded_by']) > 0:
				continue

			parents.append(r)

		if len(parents):
			break

	return jsonify(ok=1, hierarchy=hierarchy, parents=parents, parent_id=parent_id)

@app.route('/nearby', methods=['GET'])
def geojson_nearby():

	api_key = flask.g.mapzen_api_key

	lat = float(request.args.get('latitude'))
	lng = float(request.args.get('longitude'))
	name = request.args.get('name')

	results = []
	result_ids = []

	method = 'whosonfirst.places.getByLatLon'
	params = { 'api_key': api_key, 'method': method, 'latitude': lat, 'longitude': lng, 'placetype': 'neighbourhood' }
	extras = 'addr:full,wof:tags'

	rsp = requests.get('https://whosonfirst-api.mapzen.com/', params=params)
	data = json.loads(rsp.content)

	if (data['places'] and len(data['places']) > 0):
		for neighbourhood in data['places']:

			wofid = neighbourhood['wof:id']
			method = 'whosonfirst.places.search'
			params = { 'api_key': api_key, 'method': method, 'neighbourhood_id': wofid, 'names': name, 'extras': extras, 'placetype': 'venue' }

			rsp = requests.get('https://whosonfirst-api.mapzen.com/', params=params)
			data = json.loads(rsp.content)

			if data['places']:
				for pl in data['places']:
					if pl['wof:id'] not in result_ids:
						result_ids.append(pl['wof:id'])
						results.append(pl)

	method = "whosonfirst.places.getNearby"
	params = { 'api_key': api_key, 'method': method, 'latitude': lat, 'longitude': lng, 'placetype': 'venue', 'radius': 100, 'extras': extras }

	rsp = requests.get('https://whosonfirst-api.mapzen.com/', params=params)
	print rsp.content
	data = json.loads(rsp.content)

	if (data['places'] and len(data['places']) > 0):
		for pl in data['places']:
			if pl['wof:id'] not in result_ids:
				result_ids.append(pl['wof:id'])
				results.append(pl)

	return jsonify(ok=1, results=results)

if __name__ == "__main__":
	import sys
	import optparse
	import ConfigParser

	opt_parser = optparse.OptionParser()

	opt_parser.add_option('-p', '--port', dest='port', action='store', default=8181, help='')
	opt_parser.add_option('-d', '--dir', dest='dir', action='store', default='/usr/local/mapzen/whosonfirst-www-boundaryissues/pending/', help='wof_pending_dir')
	opt_parser.add_option('-k', '--api-key', dest='api_key', action='store', default='', help='mapzen_api_key')
	opt_parser.add_option('-v', '--verbose', dest='verbose', action='store_true', default=False, help='Be chatty (default is false)')

	options, args = opt_parser.parse_args()

	if options.verbose:
		logging.basicConfig(level=logging.DEBUG)
	else:
		logging.basicConfig(level=logging.INFO)

	os.environ['WOF_PENDING_DIR'] = options.dir
	os.environ['MAPZEN_API_KEY'] = options.api_key
	port = int(options.port)

	app.run(port=port)
