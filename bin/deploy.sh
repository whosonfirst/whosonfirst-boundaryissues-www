#!/bin/sh

# Usage: deploy.sh [branch]
#        [branch] defaults to master

if [ -z "$1" ]
  then
    branch="master"
else
    branch="$1"
fi

cd /usr/local/mapzen/whosonfirst-www-boundaryissues
git fetch
git checkout ${branch}
git pull origin ${branch}
sudo supervisorctl restart all
sudo /etc/init.d/wof-geojson-server.sh restart
sudo /etc/init.d/wof-pubsocketd-server.sh restart

# Make sure we have the latest WOF Python libraries
wof-pylibs

if [ $? -eq 1 ] ; then
  cd /usr/local/mapzen/py-mapzen-whosonfirst
  git pull origin master
  sudo python setup.py install

  # This is because pip-utils is still on a separate branch
  cd /usr/local/mapzen/py-mapzen-whosonfirst-pip-utils
  git pull
  sudo python setup.py install
fi
