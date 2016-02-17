#!/bin/sh

PYTHON=`which python`
PERL=`which perl`

WHOAMI=`${PYTHON} -c 'import os, sys; print os.path.realpath(sys.argv[1])' $0`
PARENT=`dirname $WHOAMI`

PROJECT=`dirname $PARENT`
PROJECT_NAME=`basename ${PROJECT}`

SERVICES="${PROJECT}/services"

PIP_SERVICE="${SERVICES}/pip-server"
PIP_SERVER="${PIP_SERVICE}/wof-pip-server"

PIP_INITD="${PIP_SERVICE}/wof-pip-server.sh"

if [ -f ${PIP_INITD} ]
then
    mv ${PIP_INITD} ${PIP_INITD}.bak
fi

cp ${PIP_INITD}.example ${PIP_INITD}

# THIS WILL BREAK WITH MULTIPLE REPOSITORIES... (20160217/thisisaaronland)
${PERL} -p -i -e "s!__WHOSONFIRST_DATA__!/usr/local/mapzen/whosonfirst-data/data!g" ${PIP_INITD}

# UHHHHHHH... (20160217/thisisaaronland)
${PERL} -p -i -e "s!__WHOSONFIRST_METAFILES__!!g" ${PIP_INITD}

${PERL} -p -i -e "s!__PIPSERVER_USER__!www-data!g" ${PIP_INITD}
${PERL} -p -i -e "s!__PIPSERVER_DAEMON__!${PIP_SERVER}!g" ${PIP_INITD}
${PERL} -p -i -e "s!__PIPSERVER_ARGS__!!g" ${PIP_INITD}

if [ -L /etc/init.d/wof-pip-server.sh ]
then
    sudo rm /etc/init.d/wof-pip-server.sh
fi

sudo ln -s ${PIP_INITD} /etc/init.d/wof-pip-server.sh

