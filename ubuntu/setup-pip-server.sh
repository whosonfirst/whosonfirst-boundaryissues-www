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

# TO DO ... TBD
#
# Read in keys to build bundle URLs
# Fetch bundle(s)
# Expand in to ... always just assume /usr/local/mapzen/whosonfirst-data ?
# Build METAFILES (below) accordingly
#
# See also:
# https://whosonfirst.mapzen.com/bundles/#working-with
# https://github.com/whosonfirst/fuse-whosonfirst-fs/blob/master/README.md

METAFILES=`ls -a /usr/local/mapzen/whosonfirst-meta/*-latest.csv | grep -v concordances | tr '\n' ' '`
# METAFILES="/usr/local/whosonfirst-meta/wof-country-latest.csv"

PIP_ARGS="-cache_size 20000"

cp ${PIP_INITD}.example ${PIP_INITD}
chmod 755 ${PIP_INITD}

${PERL} -p -i -e "s!__WHOSONFIRST_DATA__!/usr/local/mapzen/whosonfirst-data/!g" ${PIP_INITD}
${PERL} -p -i -e "s!__WHOSONFIRST_METAFILES__!${METAFILES}!g" ${PIP_INITD}

${PERL} -p -i -e "s!__PIPSERVER_USER__!www-data!g" ${PIP_INITD}
${PERL} -p -i -e "s!__PIPSERVER_DAEMON__!${PIP_SERVER}!g" ${PIP_INITD}
${PERL} -p -i -e "s!__PIPSERVER_ARGS__!${PIP_ARGS}!g" ${PIP_INITD}

if [ -L /etc/init.d/wof-pip-server.sh ]
then
    sudo rm /etc/init.d/wof-pip-server.sh
fi

sudo ln -s ${PIP_INITD} /etc/init.d/wof-pip-server.sh

sudo update-rc.d wof-pip-server.sh defaults

# See the way we're assuming names of files here? Yeah, that's not awesome
# but it will have to do for now... (20160217/thisisaaronland)

if [ -f /var/run/wof-pip-server.sh.pid ]
then
    sudo /etc/init.d/wof-pip-server.sh stop
fi

sudo /etc/init.d/wof-pip-server.sh start

exit 0
