# Sky Box setup

Here is a list of things required to set up the "[Sky Box](https://52.91.189.86/)," mostly for my own future benefit. This is an update from a previously-installed iteration of Boundary Issues, so a lot of this was just picking up where we left off from our earlier [`make setup`](https://github.com/whosonfirst/whosonfirst-www-boundaryissues/blob/flamework/Makefile#L8).

```
sudo usermod -a -G opsworks dphiffer
cd /usr/local/mapzen/
sudo chgrp opsworks .
sudo chmod g+s .
sudo chmod g+w .
cd /usr/local/mapzen/whosonfirst-www-boundaryissues/
git remote set-url origin https://github.com/whosonfirst/whosonfirst-www-boundaryissues.git
git pull origin flamework
cd /usr/local/mapzen/
git clone https://github.com/whosonfirst-data/whosonfirst-data-venue-us-new-york.git
sudo chown -R www-data:mapzen ./whosonfirst-data-venue-us-new-york/
sudo chmod -R g+s ./whosonfirst-data-venue-us-new-york/
sudo chmod -R g+w ./whosonfirst-data-venue-us-new-york/
cd /usr/local/mapzen/whosonfirst-www-boundaryissues/
ln -s /usr/local/mapzen/whosonfirst-data-venue-us-new-york data
emacs www/include/secrets.php
```

This is where I added the OAuth ID and secret from the [GitHub application](https://github.com/settings/developers):

```
$GLOBALS['cfg']['github_oauth_key'] = 'xxx';
$GLOBALS['cfg']['github_oauth_secret'] = 'yyy';
```

This next command will require that you know the root MySQL password.

```
mysql -u root -p boundaryissues < schema/alters/20160301.db_main.schema
```

Next I needed to check out a slightly modified version of [py-mapzen-whosonfirst-search](https://github.com/whosonfirst/py-mapzen-whosonfirst-search/tree/dphiffer/boundary-issues):

```
cd /usr/local/mapzen/
git clone https://github.com/whosonfirst/py-mapzen-whosonfirst-search.git
git checkout dphiffer/boundary-issues
sudo python setup.py install
```

Not sure why, but the install script threw [an error](https://gist.github.com/dphiffer/88894dba90d2732984e9) at the very end, but things seem to work so... I guess we can just move along?

The next steps are to update the ES schema and then index the documents.

```
cd /usr/local/mapzen/
git clone https://github.com/whosonfirst/es-whosonfirst-schema.git
cd es-whosonfirst-schema/
./bin/reload-schema.sh boundaryissues
cd /usr/local/mapzen/whosonfirst-www-boundaryissues/
./ubuntu/setup-elasticsearch-index.sh
```

Next up: `mapzen.whosonfirst.pip.utils`

```
cd /usr/local/mapzen/
git clone https://github.com/whosonfirst/py-mapzen-whosonfirst-pip-utils.git
git checkout dphiffer/boundary-issues
sudo python setup.py install
```

### Gearman

```
sudo apt-get install -y gearman-job-server php5-gearman
```

### Supervisor

```
sudo apt-get install -y supervisor
sudo emacs /etc/supervisor/supervisord.conf
```

Add the following config:

```
[program:boundaryissues_gearman_worker]
command=/usr/bin/php -f /usr/local/mapzen/whosonfirst-www-boundaryissues/bin/gearman_worker.php
user=www-data
```

Instruct supervisord to reload its configuration:

```
sudo supervisorctl reload
```

Set up the logfile.

```
sudo touch /var/log/boundaryissues_gearman.log
sudo chown www-data:www-data /var/log/boundaryissues_gearman.log
```

### Elasticsearch reindexing

Requirement: [stream2es](https://github.com/elastic/stream2es)

```
curl -O https://download.elasticsearch.org/stream2es/stream2es
chmod +x stream2es
sudo mv stream2es /usr/local/bin/
```
