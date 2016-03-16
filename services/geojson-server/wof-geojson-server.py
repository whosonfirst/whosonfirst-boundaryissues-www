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
    return fh.read()
@app.route('/save', methods=['POST'])
def geojson_save():
    g = request.form['geojson']
    f = geojson.loads(g)

    # Does the input pass the smell check?
    validation = geojson.is_valid(f)
    if (validation['valid'] == 'no'):
        logging.error("GeoJSON input is not valid: %s" % validation['message'])
        return null

    # This assumes that a 'data' symlink has been created in the Boundary Issues
    # directory (20160307/dphiffer)
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
    return "ok"
@app.route('/pip', methods=['GET'])
def geojson_hierarchy():
    data_endpoint = 'https://whosonfirst.mapzen.com/data/'
    lat = float(request.args.get('latitude'))
    lng = float(request.args.get('longitude'))
    placetype = request.args.get('placetype')

    if (mapzen.whosonfirst.placetypes.is_valid_placetype(placetype) == False):
        return "Error: invalid placetype."

    parents = mapzen.whosonfirst.pip.utils.get_reverse_geocoded(lat, lng, placetype)
    hierarchy = mapzen.whosonfirst.pip.utils.get_hierarchy(parents, data_endpoint)
    return jsonify(hierarchy=hierarchy, parents=parents)

if __name__ == "__main__":
    app.run(port=8181)
