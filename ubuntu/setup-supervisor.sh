#!/bin/sh

WHOAMI=`python -c 'import os, sys; print os.path.realpath(sys.argv[1])' $0`

UBUNTU=`dirname $WHOAMI`
PROJECT=`dirname $UBUNTU`

PROJECT_NAME=`basename ${PROJECT}`

SUPERVISOR="${PROJECT}/supervisor"
CONF="${SUPERVISOR}/${PROJECT_NAME}.conf"

sudo apt-get update
sudo apt-get install -y supervisor

if [ ! -f ${CONF}.example ]
then
    echo "missing example ${CONF}"
    exit 1
fi

if [ -f ${CONF} ]
then
    cp ${CONF} ${CONF}.bak
fi

cp ${CONF}.example ${CONF}

if [ -L /etc/supervisor/conf.d/${PROJECT_NAME}.conf ]
then
    sudo rm /etc/supervisor/conf.d/${PROJECT_NAME}.conf
fi

sudo ln -s ${CONF} /etc/supervisor/conf.d/${PROJECT_NAME}.conf

sudo supervisorctl reload
