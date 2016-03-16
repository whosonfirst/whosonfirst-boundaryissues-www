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
    g = request.form['geojson']
    f = geojson.loads(g)
    e = mapzen.whosonfirst.geojson.encoder(precision=None)
    fh = StringIO.StringIO()
    e.encode_feature(f, fh)
    fh.seek(0)
    encoded = fh.read()
    return jsonify(ok=1, encoded=encoded)
@app.route('/save', methods=['POST'])
def geojson_save():
    g = request.form['geojson']
    f = geojson.loads(g)

    # Does the input pass the smell check?
    validation = geojson.is_valid(f)
    if (validation['valid'] == 'no'):
        error = "GeoJSON doesn't smell right: %s" % validation['message']
        logging.error(error)
        return jsonify(ok=0, error=error)

    ff = mapzen.whosonfirst.export.flatfile(root, debug=False)
    path = ff.export_feature(f)

    # Repeat back the file we just wrote
    gf = open(path)
    return gf.read()
@app.route('/update_elasticsearch', methods=['POST'])
def geojson_update_elasticsearch():
    id = int(request.form['id'])
    path = mapzen.whosonfirst.utils.id2abspath(root, id)
    idx = mapzen.whosonfirst.search.index()
    try:
        idx.index_file(path)
    except Exception, e:
        logging.error("failed to index %s, because %s" % (path, e))
    return jsonify(ok=1)
@app.route('/pip', methods=['GET'])
def geojson_hierarchy():
    data_endpoint = 'https://whosonfirst.mapzen.com/data/'
    lat = float(request.args.get('latitude'))
    lng = float(request.args.get('longitude'))
    placetype = request.args.get('placetype')

    if (mapzen.whosonfirst.placetypes.is_valid_placetype(placetype) == False):
        return jsonify(ok=0, error="What is that placetype?")

    parents = mapzen.whosonfirst.pip.utils.get_reverse_geocoded(lat, lng, placetype)
    hierarchy = mapzen.whosonfirst.pip.utils.get_hierarchy(parents, data_endpoint)
    return jsonify(ok=1, hierarchy=hierarchy, parents=parents)

if __name__ == "__main__":
    app.run(port=8181)
