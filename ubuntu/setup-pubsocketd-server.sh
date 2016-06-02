#!/bin/sh

# See also: https://github.com/whosonfirst/go-pubsocketd

PYTHON=`which python`
PERL=`which perl`

WHOAMI=`${PYTHON} -c 'import os, sys; print os.path.realpath(sys.argv[1])' $0`
PARENT=`dirname $WHOAMI`

PROJECT=`dirname $PARENT`
PROJECT_NAME=`basename ${PROJECT}`

SERVICES="${PROJECT}/services"

PUBSOCKETD_SERVICE="${SERVICES}/pubsocketd-server"
PUBSOCKETD_SERVER="${PUBSOCKETD_SERVICE}/wof-pubsocketd-server"
PUBSOCKETD_INITD="${PUBSOCKETD_SERVICE}/wof-pubsocketd-server.sh"

if [ -f ${PUBSOCKETD_INITD} ]
then
    mv ${PUBSOCKETD_INITD} ${PUBSOCKETD_INITD}.bak
fi

if [ -z "$1" ]
then
	echo "You can specify an origin argument like this: setup-pubsocket-server.sh [origin]"
	PUBSOCKETD_ORIGIN="https://localhost:8990"
	echo "Defaulting to ${PUBSOCKETD_ORIGIN}"
else
	PUBSOCKETD_ORIGIN="$1"
fi

PUBSOCKETD_ARGS="-ws-origin=${PUBSOCKETD_ORIGIN} -rs-channel=notifications"

cp ${PUBSOCKETD_INITD}.example ${PUBSOCKETD_INITD}
chmod 755 ${PUBSOCKETD_INITD}

${PERL} -p -i -e "s!__PUBSOCKETD_SERVER_USER__!www-data!g" ${PUBSOCKETD_INITD}
${PERL} -p -i -e "s!__PUBSOCKETD_SERVER_DAEMON__!${PUBSOCKETD_SERVER}!g" ${PUBSOCKETD_INITD}
${PERL} -p -i -e "s!__PUBSOCKETD_SERVER_ARGS__!${PUBSOCKETD_ARGS}!g" ${PUBSOCKETD_INITD}

if [ -L /etc/init.d/wof-pubsocketd-server.sh ]
then
    sudo rm /etc/init.d/wof-pubsocketd-server.sh
fi

sudo ln -s ${PUBSOCKETD_INITD} /etc/init.d/wof-pubsocketd-server.sh

sudo update-rc.d wof-pubsocketd-server.sh defaults

# See the way we're assuming names of files here? Yeah, that's not awesome
# but it will have to do for now... (20160217/thisisaaronland)

if [ -f /var/run/wof-pubsocketd-server.pid ]
then
    sudo /etc/init.d/wof-pubsocketd-server.sh stop
fi

sudo /etc/init.d/wof-pubsocketd-server.sh start

exit 0
