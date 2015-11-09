# Updating any one Who's On First record

This document outlines what needs to happen when any one WOF record is updated. How this applies to multiple WOF records being updated simultaenously, and the ordering and scheduling of events to prevent thundering herds, remains TBD.

Currently these are all discrete steps (as they should be) but need to be stitched together in a handy one-stop-shopping interface for use by command-line tools, offline tasks and web interfaces alike.

## Validate and format file on disk

### Relevant bits

* https://github.com/whosonfirst/py-mapzen-whosonfirst-export
* https://github.com/whosonfirst/py-mapzen-whosonfirst-validator
* https://github.com/whosonfirst/py-mapzen-whosonfirst-geojson

Note that we are using a bespoke GeoJSON encoder, in Python, to indent everything _except_ the geometries. Determining whether or not we can do the same in another language has seemed like yak-shaving so right now converting a data structure in to bytes written to disk is done in Python.

The same dynamic exists for the validation piece. If you find yourself thinking _I know, let's create a declarative schema that we can load from any language to do validation_ I would suggest you step away from the computer and go for a walk long enough to disabuse yourself of the idea.

### Related bits

* https://github.com/whosonfirst/py-mapzen-whosonfirst-export/blob/master/scripts/wof-exportify

## Copy to S3

### Relevant bits

* http://docs.pythonboto.org/en/latest/s3_tut.html
* https://github.com/whosonfirst/go-whosonfirst-s3

### Issues

* AWS credentials

## Commit to Git(Hub)

I don't really have a good answer for this one yet, absent shelling out to `git`. It is further complicated by the part where Git 1.9.x seems to be the default installation whereas Git 2.6.x is what's actually been blessed as stable and further addresses speed and performance issues when working with a bazillion tiny files.

The language bindings for `libgit2` make it seem like we ought to be able to do better than shelling out and without enforcing language dependencies but that might just be wishful thinking on my part...

### Relevant bits

* https://libgit2.github.com/

### Issues

* GitHub credentials

## Index in Elasticsearch (for the Spelunker)

### Relevant bits

* https://github.com/whosonfirst/py-mapzen-whosonfirst-search

### Issues

* Network ACLs because ES has no security

## Update meta files (in the `whosonfirst-data` respository)

Currently we are updating meta files in batch mode on a per-placetype basis. We should add functionality to update (n) individual rows in an existing CSV meta file.

### Relevant bits

* https://github.com/whosonfirst/py-mapzen-whosonfirst-utils/blob/master/scripts/wof-placetype-to-csv

### Related bits

* https://github.com/whosonfirst/py-mapzen-whosonfirst-utils/issues/5
* https://github.com/whosonfirst/py-mapzen-whosonfirst-utils/issues/4
