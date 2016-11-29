#!/usr/bin/env python

import StringIO
import sys
import logging
import os
import re
import flask
from flask import Flask, request, jsonify

import geojson
import mapzen.whosonfirst.geojson
import mapzen.whosonfirst.export
import mapzen.whosonfirst.search
import mapzen.whosonfirst.utils
import mapzen.whosonfirst.placetypes
import mapzen.whosonfirst.pip.utils

app = Flask(__name__)

@app.before_request
def init():
	flask.g.wof_pending_dir = os.environ.get('WOF_PENDING_DIR', '/usr/local/mapzen/whosonfirst-www-boundaryissues/pending/')

@app.route('/encode', methods=['POST'])
def geojson_encode():

	try:
		g = request.form['geojson']
		f = geojson.loads(g)
	except Exception, e:
		error = "failed to load geojson, because %s" % e
		logging.error(error)
		return jsonify(ok=0, error=error)

	# Does the input pass the smell check?
	validation = geojson.is_valid(f)

	if (validation['valid'] == 'no'):
		error = "GeoJSON doesn't smell right: %s" % validation['message']
		logging.error(error)
		return jsonify(ok=0, error=error)

	e = mapzen.whosonfirst.geojson.encoder(precision=None)

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

	# Does the input pass the smell check?
	validation = geojson.is_valid(f)

	if (validation['valid'] == 'no'):
		error = "GeoJSON doesn't smell right: %s" % validation['message']
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

@app.route('/pip', methods=['GET'])
def geojson_hierarchy():
	data_endpoint = 'https://whosonfirst.mapzen.com/data/'
	lat = float(request.args.get('latitude'))
	lng = float(request.args.get('longitude'))
	wof_id = request.args.get('wof_id')
	placetype = request.args.get('placetype')
	pip_server = mapzen.whosonfirst.pip.server(hostname='pip.mapzen.com', scheme='https', port=443)

	if (mapzen.whosonfirst.placetypes.is_valid_placetype(placetype) == False):
		return jsonify(ok=0, error="What is that placetype?")

	try:
		parents = mapzen.whosonfirst.pip.utils.get_reverse_geocoded(lat, lng, placetype, pip_server=pip_server)
	except Exception, e:
		error = "failed to determine parents, because %s" % e
		logging.error(error)
		return jsonify(ok=0, error=error)

	try:
		hierarchy = mapzen.whosonfirst.pip.utils.get_hierarchy(parents, wofid=wof_id, placetype=placetype)
	except Exception, e:
		error = "failed to determine hierarchy, because %s" % e
		logging.error(error)
		return jsonify(ok=0, error=error)

	return jsonify(ok=1, hierarchy=hierarchy, parents=parents)

if __name__ == "__main__":
	import sys
	import optparse
	import ConfigParser

	opt_parser = optparse.OptionParser()

	opt_parser.add_option('-p', '--port', dest='port', action='store', default=8181, help='')
	opt_parser.add_option('-d', '--dir', dest='dir', action='store', default='/usr/local/mapzen/whosonfirst-www-boundaryissues/pending/', help='wof_pending_dir')
	opt_parser.add_option('-v', '--verbose', dest='verbose', action='store_true', default=False, help='Be chatty (default is false)')

	options, args = opt_parser.parse_args()

	if options.verbose:
		logging.basicConfig(level=logging.DEBUG)
	else:
		logging.basicConfig(level=logging.INFO)

	os.environ['WOF_PENDING_DIR'] = options.dir
	port = int(options.port)

	app.run(port=port)
