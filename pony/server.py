import StringIO
import sys
from flask import Flask, request

import geojson
import mapzen.whosonfirst.geojson

app = Flask(__name__)

@app.route('/geojson-encode', methods=['POST'])
def geojson_encode():
    g = request.form['geojson']
    f = geojson.loads(g)
    e = mapzen.whosonfirst.geojson.encoder(precision=None)
    fh = StringIO.StringIO()
    e.encode_feature(f, fh)
    fh.seek(0)
    return fh.read()

if __name__ == "__main__":
    app.run()
