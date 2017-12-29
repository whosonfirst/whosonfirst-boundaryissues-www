#!/bin/sh

WHOAMI=`python -c 'import os, sys; print os.path.realpath(sys.argv[1])' $0`

UBUNTU=`dirname $WHOAMI`
PROJECT=`dirname $UBUNTU`

PROJECT_NAME=`basename ${PROJECT}`

CONF_DIR="${PROJECT}/config"
CONF="${CONF_DIR}/${PROJECT_NAME}-apache.conf"

if [ $1 = "nossl" ]
then
	EXAMPLE_CONF="${CONF_DIR}/${PROJECT_NAME}-apache-nossl.conf.example"
else
	EXAMPLE_CONF="${CONF_DIR}/${PROJECT_NAME}-apache.conf.example"
fi

if [ ! -f ${EXAMPLE_CONF} ]
then
    echo "missing ${EXAMPLE_CONF}"
    exit 1
fi

if [ -f ${CONF} ]
then
    cp ${CONF} ${CONF}.bak
fi

cp ${EXAMPLE_CONF} ${CONF}

perl -p -i -e "s!__PROJECT_ROOT__!${PROJECT}!" ${CONF}
perl -p -i -e "s!__PROJECT_NAME__!${PROJECT_NAME}!" ${CONF}

if [ -L /etc/apache2/sites-enabled/000-default.conf ]
then
    sudo rm /etc/apache2/sites-enabled/000-default.conf
fi

if [ -L /etc/apache2/sites-enabled/${PROJECT_NAME}.conf ]
then
    sudo rm /etc/apache2/sites-enabled/${PROJECT_NAME}.conf
fi

sudo ln -s ${CONF} /etc/apache2/sites-enabled/${PROJECT_NAME}.conf

sudo /etc/init.d/apache2 restart
