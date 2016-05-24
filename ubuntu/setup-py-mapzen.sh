#!/bin/sh

WHOAMI=`python -c 'import os, sys; print os.path.realpath(sys.argv[1])' $0`
PARENT=`dirname $WHOAMI`

PROJECT=`dirname $PARENT`
PROJECT_NAME=`basename ${PROJECT}`

GIT=`which git`

if [ -d /usr/local/mapzen/py-mapzen-whosonfirst ]
then
    cd /usr/local/mapzen/py-mapzen-whosonfirst
    git pull origin master
else
    git clone git@github.com:whosonfirst/py-mapzen-whosonfirst.git /usr/local/mapzen/py-mapzen-whosonfirst
    cd /usr/local/mapzen/py-mapzen-whosonfirst
fi

sudo python ./setup.py install

# Install pip so we can avoid SSL warnings when running wof-pylibs
sudo apt-get update
sudo apt-get install -y python-pip
sudo pip install pyopenssl ndg-httpsclient pyasn1

cd -
