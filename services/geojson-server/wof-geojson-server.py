#!/usr/bin/env python

import StringIO
import sys
import logging
from flask import Flask, request, jsonify

import geojson
import mapzen.whosonfirst.geojson
import mapzen.whosonfirst.export
import mapzen.whosonfirst.search
import mapzen.whosonfirst.utils
import mapzen.whosonfirst.placetypes
import mapzen.whosonfirst.pip.utils

# This assumes that a 'data' symlink has been created in the Boundary Issues
# directory (20160307/dphiffer)
root = "/usr/local/mapzen/whosonfirst-www-boundaryissues/data"
app = Flask(__name__)

@app.route('/encode', methods=['POST'])
def geojson_encode():

	try:
		g = request.form['geojson']
		f = geojson.loads(g)
	except Exception, e:
		error = "failed to load geojson, because %s" % e
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
		f = geojson.loads(g)
	except Exception, e:
		err = "failed to load geojson, because %s" % e
		return jsonify(ok=0, error=err)

	# Does the input pass the smell check?
	validation = geojson.is_valid(f)

	if (validation['valid'] == 'no'):
		error = "GeoJSON doesn't smell right: %s" % validation['message']
		logging.error(error)
		return jsonify(ok=0, error=error)

	try:
		ff = mapzen.whosonfirst.export.flatfile(root, debug=False)
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
	placetype = request.args.get('placetype')

	if (mapzen.whosonfirst.placetypes.is_valid_placetype(placetype) == False):
		return jsonify(ok=0, error="What is that placetype?")

	try:
		parents = mapzen.whosonfirst.pip.utils.get_reverse_geocoded(lat, lng, placetype)
	except Exception, e:
		error = "failed to determine parents, because %s" % e
		logging.error(error)
		return jsonify(ok=0, error=error)

	try:
		hierarchy = mapzen.whosonfirst.pip.utils.get_hierarchy(parents, data_endpoint)
	except Exception, e:
		error = "failed to determine hierarchy, because %s" % e
		logging.error(error)
		return jsonify(ok=0, error=error)

	return jsonify(ok=1, hierarchy=hierarchy, parents=parents)

if __name__ == "__main__":
	app.run(port=8181)
