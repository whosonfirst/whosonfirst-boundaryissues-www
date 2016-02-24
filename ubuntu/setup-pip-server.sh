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

# METAFILES=`ls -a /usr/local/mapzen/whosonfirst-meta/*-latest.csv | grep -v concordances | tr '\n' ' '`
# METAFILES="/usr/local/whosonfirst-meta/wof-country-latest.csv"

# So here's a thing we're trying out...
#
# Read in keys to build bundle URLs
# Fetch bundle(s)
# Expand in to ... always just assume /usr/local/mapzen/whosonfirst-data ?
# Build METAFILES accordingly
#
# As in:
# ./ubuntu/setup-pip-server.sh microhood neighbourhood locality
#
# See also:
# https://whosonfirst.mapzen.com/bundles/#working-with
# https://github.com/whosonfirst/fuse-whosonfirst-fs/blob/master/README.md

WOF_DATA=/usr/local/mapzen/whosonfirst-data
DATA=${WOF_DATA}/data
META=${WOF_DATA}/meta

for DEST in ${DATA} ${META}
do

    if [ ! -d ${DEST} ]
    then
        mkdir -p ${DEST}
    fi
done

sudo chgrp -R www-data ${DATA}
sudo chmod -R g+w ${DATA}
sudo chmod -R g+s www-data

for PT in $@
do

    BUNDLE="wof-${PT}-latest-bundle"
    COMPRESSED="${BUNDLE}.tar.bz2"

    if [ -e ${COMPRESSED} ]
    then
        echo "remove ${COMPRESSED}"
        rm -f ${COMPRESSED}
    fi

    if [ -d ${BUNDLE} ]
    then
        echo "remove ${BUNDLE}"
        rm -rf ${BUNDLE}
    fi

    echo "fetch ${COMPRESSED}"
    curl -o ${COMPRESSED} https://whosonfirst.mapzen.com/bundles/${COMPRESSED}

    echo "expand ${COMPRESSED}"
    tar -xvjf ${COMPRESSED}

    echo "sync ${BUNDLE}"
    rsync -av ${BUNDLE}/data/ ${DATA}/

    cp ${BUNDLE}/wof-${PT}-latest.csv ${META}/wof-${PT}-latest.csv
    chmod 644 ${META}/wof-${PT}-latest.csv

    rm -rf ${BUNDLE}
    rm -f ${COMPRESSED}
done

METAFILES=`ls -a ${META}/*-latest.csv | grep -v concordances | tr '\n' ' '`

PIP_ARGS="-cache_size 20000"

cp ${PIP_INITD}.example ${PIP_INITD}
chmod 755 ${PIP_INITD}

${PERL} -p -i -e "s!__WHOSONFIRST_DATA__!${DATA}!g" ${PIP_INITD}
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

if [ -f /var/run/wof-pip-server.pid ]
then
    sudo /etc/init.d/wof-pip-server.sh stop
fi

sudo /etc/init.d/wof-pip-server.sh start

exit 0
