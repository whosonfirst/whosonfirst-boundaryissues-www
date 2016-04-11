# Updating the Elasticsearch schema

After updating the field mappings stored in the [es-whosonfirst-schema](https://github.com/whosonfirst/es-whosonfirst-schema) repo, you will need to update the **sky box** like this:

First, make sure you have the dependency [stream2es](https://github.com/elastic/stream2es) somewhere in your path (e.g., `/usr/local/bin`)

Second, this is going to take a long time. You should use `screen` (or `tmux` or something).

```
screen -S index
```

Okay, now you can do the rest of this stuff and not worry about your terminal session timing out.

```
cd /usr/local/mapzen/es-whosonfirst-schema/
git pull
./bin/update-schema.sh boundaryissues
```

While that's running, you can detach from your `screen` session with `ctrl-A ctrl-D` and then check in on it later with `screen -x index`.

Once the index finishes updating, you should find that the file `/usr/local/mapzen/es-whosonfirst-schema/BOUNDARYISSUES_INDEX_VERSION` has been incremented by one, and the number it contains should match the index name: `whosonfirst_v[n]` (which is aliased as the index `whosonfirst`).
