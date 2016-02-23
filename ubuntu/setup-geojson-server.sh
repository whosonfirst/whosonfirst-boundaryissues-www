#!/bin/sh

PYTHON=`which python`
PERL=`which perl`

WHOAMI=`${PYTHON} -c 'import os, sys; print os.path.realpath(sys.argv[1])' $0`
PARENT=`dirname $WHOAMI`

PROJECT=`dirname $PARENT`
PROJECT_NAME=`basename ${PROJECT}`

SERVICES="${PROJECT}/services"

GEOJSON_SERVICE="${SERVICES}/geojson-server"
GEOJSON_SERVER="${GEOJSON_SERVICE}/wof-geojson-server.py"
GEOJSON_INITD="${GEOJSON_SERVICE}/wof-geojson-server.sh"

sudo apt-get install python-flask python-requests

if [ -f ${GEOJSON_INITD} ]
then
    mv ${GEOJSON_INITD} ${GEOJSON_INITD}.bak
fi

GEOJSON_ARGS=""

cp ${GEOJSON_INITD}.example ${GEOJSON_INITD}
chmod 755 ${GEOJSON_INITD}

${PERL} -p -i -e "s!__GEOJSON_SERVER_USER__!www-data!g" ${GEOJSON_INITD}
${PERL} -p -i -e "s!__GEOJSON_SERVER_DAEMON__!${GEOJSON_SERVER}!g" ${GEOJSON_INITD}
${PERL} -p -i -e "s!__GEOJSON_SERVER_ARGS__!${GEOJSON_ARGS}!g" ${GEOJSON_INITD}

if [ -L /etc/init.d/wof-geojson-server.sh ]
then
    sudo rm /etc/init.d/wof-geojson-server.sh
fi

sudo ln -s ${GEOJSON_INITD} /etc/init.d/wof-geojson-server.sh

sudo update-rc.d wof-geojson-server.sh defaults

# See the way we're assuming names of files here? Yeah, that's not awesome
# but it will have to do for now... (20160217/thisisaaronland)

if [ -f /var/run/wof-geojson-server.sh.pid ]
then
    sudo /etc/init.d/wof-geojson-server.sh stop
fi

sudo /etc/init.d/wof-geojson-server.sh start

exit 0
