# Boundary Issues design spec

In broad strokes - which means none of this has been tested with running code yet:

## Moving pieces

### WWW/API

There is a web server with some kind of REST-ish API. In many ways it needs to be a functional clone of [whosonfirst-www-spelunker]() but with an fancy JS editing interface and something we can `POST` GeoJSON `Feature` blobs to.

It is tempting just to make this a Flask application since it's easy to cookie-cutter copy/paste the spelunker and to the extent that the two applications share functionality that can be abstracted in to a separate library.

It is equally tempting to make this piece a Flamework application since it makes the unholy trinity of an actual web application + API dispatch + auth a manageble problem. Assuming that the web application is just a thin wrapper around everything described below we could adopt the following approach:

* All processing of actual data (including validation) happens in Python, because libraries
* We decouple jobs (and their status) from the editorial workflow described below
* The client is expected to poll for the status of an updated (unless it gets a notification via something like WebSockets)

Anyway, we're not going to make that decision today. Onwards...

### PUT-er (basically part of WWW/API)

As in take a blob of GeoJSON and do some basic sanity checking and put it somewhere on disk where `watchdog.py` or equivalent can process it as the operating system allows. It needs to:

1. Validate incoming data
2. Generate a diff using `py-mapzen-whosonfirst-diff` (which doesn't exist yet
3. Generate a job ID and lock using `py-mapzen-whosonfirst-editorial` (which doesn't exist yet)
4. Write to disk, in a pending folder with something a name like `WOFID`-`JOBID`.geojson

### POST-er (part 1/2)

Basically a `watchdog.py` script that:

0. Update the edit history as necessary by job ID
1. Reads an incoming (newly created) file
2. Ensures a corresponding WOF ID and job ID are valid
3. Validates the incoming data (note, we will perform the same validation at almost every step)
4. Sends file to the PUB-er

Or you know any fancy-pants queueing system, because see below inre: thundering herds.

### PUB-er

This is either a command-line (or maybe an HTTP pony?) invocation of [py-mapzen-whosonfirst-publish]() that will:

0. Update the edit history as necessary by job ID
1. Receive input data
2. Validate input data
3. Publish to all the sources (defined in py-mapzen-whosonfirst-publish)
4. Return true or false

And yes, this is code that could be made to hold hands with the POST-er except that the POST-er is actually blocking and waiting for the response of PUB-er. Assuming a successful response it then:

### POST-er (part 2/2)

0. Update the edit history as necessary by job ID
1. Unlocks the current job
2. Check the diff (read: this is still hand-waving) to see whether changes need to be propagated and to whome.

Assuming they do then for each affected relation:

### NEXT-er

Uh...yeah. There are a couple questions here:

* How to determine what part of a file has changed
* How to update any given file (writing changes to files dynamically or ... ?)

But basically one or more files are updated and the same code that the PUT-er uses to write things to disk.

Questions:

* How quickly can we totally overwhelm this model with changes to only a single file?
* How do we prevent infinite loops?

### BROADCAST-er

Basically a combination of [py-mapzen-whosonfirst-chatterbox]() and [go-pubsocketd](https://github.com/cooperhewitt/go-pubsocketd) for relaying feedback over WebSockets to the client. Assuming that the rest of the code relies on `py-mapzen-whosonfirst-editorial` for managing state then this piece is entirely for user feedback.






