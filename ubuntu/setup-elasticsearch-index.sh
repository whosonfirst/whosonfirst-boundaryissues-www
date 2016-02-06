#!/bin/sh

WHOAMI=`python -c 'import os, sys; print os.path.realpath(sys.argv[1])' $0`

PARENT=`dirname $WHOAMI`
PROJECT=`dirname $PARENT`
DATA="${PROJECT}/data"

# this seems like a good idea... until it's not
# (20160202/thisisaaronland)
#
# if [ ! -d ${DATA} ]
# then
#     ${PARENT}/setup-data.sh
# fi

/usr/local/bin/wof-es-index -s ${DATA} -b

exit 0
