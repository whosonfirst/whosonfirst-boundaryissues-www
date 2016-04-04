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

cd -

# THIS IS A HACK WHILE WE WAIT TO MOVE / TOGGLE THE SPELUNKER FROM THE SANDBOX
# TO PROD/DEV - YOU MAY BE ASKING YOURSELF WHY THE SPELUNKER IS REACHING ITS
# BRUBBY LITTLE HANDS IN HERE AND THE ANSWER IS... YEAH, PLEASE REMOVE THIS AS
# SOON AS POSSIBLE (20160404/thisisaaronland)

if [ -d /usr/local/mapzen/py-mapzen-whosonfirst-search ]
then
    cd /usr/local/mapzen/py-mapzen-whosonfirst-search
else
    git clone git@github.com:whosonfirst/py-mapzen-whosonfirst-search.git /usr/local/mapzen/py-mapzen-whosonfirst-search
    cd /usr/local/mapzen/py-mapzen-whosonfirst
    git fetch
fi 

git checkout dphiffer/boundary-issues
git pull dphiffer/boundary-issues master

sudo python ./setup.py install

cd -
