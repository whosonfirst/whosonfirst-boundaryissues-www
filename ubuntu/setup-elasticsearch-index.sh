#!/bin/sh

WHOAMI=`python -c 'import os, sys; print os.path.realpath(sys.argv[1])' $0`

PARENT=`dirname $WHOAMI`
PROJECT=`dirname $PARENT`
DATA=$1

if [ ! -d /usr/local/mapzen/es-whosonfirst-schema ]
then
    git clone https://github.com/whosonfirst/es-whosonfirst-schema.git /usr/local/mapzen/es-whosonfirst-schema
fi

cd /usr/local/mapzen/es-whosonfirst-schema
git pull origin master
cd -

# If the schemas haven't been set already, run them for the first time

if [ ! -f /usr/local/mapzen/es-whosonfirst-schema/BOUNDARYISSUES_INDEX_VERSION ] ; then
	/usr/local/mapzen/es-whosonfirst-schema/bin/update-schema.sh boundaryissues
fi

if [ ! -f /usr/local/mapzen/es-whosonfirst-schema/OFFLINE_TASKS_INDEX_VERSION ] ; then
	/usr/local/mapzen/es-whosonfirst-schema/bin/update-schema.sh offline_tasks
fi

if [ ! -d ${DATA} ]
then
    echo "Can not find data directory ${DATA}"
    exit 1
fi

/usr/local/bin/wof-es-index -s ${DATA} -b

exit 0
