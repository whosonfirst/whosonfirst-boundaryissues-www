#!/bin/sh

WHOAMI=`python -c 'import os, sys; print os.path.realpath(sys.argv[1])' $0`

PARENT=`dirname $WHOAMI`
PROJECT=`dirname $PARENT`

DATA="${PROJECT}/data"

if [ ! -d ${DATA} ]
then
    mkdir ${DATA}
fi

TEST=`grep data ${PROJECT}/.gitignore | wc -l`

if [ ${TEST} = 0 ]
then
    echo "data" >> ${PROJECT}/.gitignore
fi

# please to make generic and move in to another script

PLACETYPES='continent country region locality neighbourhood'

if [ ! -d ${DATA} ]
then
    mkdir ${DATA}
fi

for PT in ${PLACETYPES}
do

    BUNDLE="wof-${PT}-latest-bundle"
    COMPRESSED="${BUNDLE}.tar.bz2"

    if [ -e ${COMPRESSED} ]
    then
        echo "remove ${COMPRESSED}"
        rm -i ${COMPRESSED}
    fi

    if [ -d ${BUNDLE} ]
    then
        echo "remove ${BUNDLE}"
        rm -ri ${BUNDLE}
    fi

    echo "fetch ${COMPRESSED}"
    curl -s -o ${COMPRESSED} https://whosonfirst.mapzen.com/bundles/${COMPRESSED}

    echo "expand ${COMPRESSED}"
    tar -xvjf ${COMPRESSED}

    echo "sync ${BUNDLE}"
    rsync -av ${BUNDLE}/data/ ${DATA}/

    rm -f ${COMPRESSED}
    rm -rf ${BUNDLE}

done

# end of please to make generic

exit 0
