#!/usr/bin/env python

import StringIO
import sys
from flask import Flask, request

import geojson
import mapzen.whosonfirst.geojson
import mapzen.whosonfirst.export

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
    print(g)

    # This assumes that a 'data' symlink has been created in the Boundary Issues
    # directory (20160307/dphiffer)
    ff = mapzen.whosonfirst.export.flatfile("/usr/local/mapzen/whosonfirst-www-boundaryissues/data", debug=False)
    path = ff.export_feature(f)

    # Repeat back the file we just wrote
    gf = open(path)
    return gf.read()

if __name__ == "__main__":
    app.run(port=8181)
