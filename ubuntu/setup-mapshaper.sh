#!/bin/sh

echo "PRE-EMPTIVELY DISABLING UNTIL THINGS SETTLE DOWN IN npm-LAND..."
exit 1

WHOAMI=`python -c 'import os, sys; print os.path.realpath(sys.argv[1])' $0`

UBUNTU=`dirname $WHOAMI`
PROJECT=`dirname $UBUNTU`

PROJECT_NAME=`basename ${PROJECT}`

sudo apt-get update
sudo apt-get upgrade -y

sudo apt-get install npm -y

if [ ! -x /usr/bin/node ]
then

    if [ -f /usr/bin/node ]
    then
	echo "Wait! There is a non-executable file called /usr/local/bin/node – SO CONFUSED"
	exit 1
    fi

    if [ ! -x /usr/bin/nodejs ]
    then
	echo "Oh no! I can not find nodejs"
	exit 1
    fi

    if [ -L /usr/bin/node ]
    then
	sudo rm /usr/bin/node
    fi

    sudo ln -s /usr/bin/nodejs /usr/bin/node
fi

if [ ! -d /usr/local/mapzen/mapshaper ]
then
	git clone https://github.com/mbloch/mapshaper.git /usr/local/mapzen/mapshaper
fi

cd /usr/local/mapzen/mapshaper
npm install

if [ -L /usr/local/bin/mapshaper ]
then
    sudo rm /usr/local/bin/mapshaper
fi

sudo ln -s /usr/local/mapzen/mapshaper/bin/mapshaper /usr/local/bin/mapshaper
exit 0
