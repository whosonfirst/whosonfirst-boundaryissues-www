#!/bin/sh

WHOAMI=`python -c 'import os, sys; print os.path.realpath(sys.argv[1])' $0`
PARENT=`dirname $WHOAMI`

PROJECT=`dirname $PARENT`
PROJECT_NAME=`basename ${PROJECT}`

# echo "project ${PROJECT}"
# echo "project name ${PROJECT_NAME}"

PERL=`which perl`

if [ ! -f ${PROJECT}/spelunker/spelunker.cfg ]
then
    cp ${PROJECT}/spelunker/spelunker.cfg.example ${PROJECT}/spelunker/spelunker.cfg
fi

if [ ! -f ${PROJECT}/gunicorn/${PROJECT_NAME}.cfg ]
then
    cp ${PROJECT}/gunicorn/${PROJECT_NAME}.cfg.example ${PROJECT}/gunicorn/${PROJECT_NAME}.cfg

    ${PERL} -p -i -e "s!YOUR-SPELUNKER-PORT-GOES-HERE!7777!" ${PROJECT}/gunicorn/${PROJECT_NAME}.cfg
    ${PERL} -p -i -e "s!YOUR-SPELUNKER-WWW-GOES-HERE!${PROJECT}/www!" ${PROJECT}/gunicorn/${PROJECT_NAME}.cfg
    ${PERL} -p -i -e "s!YOUR-SPELUNKER-CONFIG-NAME-GOES-HERE!${PROJECT}/spelunker/spelunker.cfg!" ${PROJECT}/gunicorn/${PROJECT_NAME}.cfg
fi

if [ ! -f ${PROJECT}/init.d/${PROJECT_NAME}.sh ]
then

    cp ${PROJECT}/init.d/${PROJECT_NAME}.sh.example ${PROJECT}/init.d/${PROJECT_NAME}.sh

    ${PERL} -p -i -e "s!YOUR-SPELUNKER-NAME!${PROJECT_NAME}!g" ${PROJECT}/init.d/${PROJECT_NAME}.sh
    ${PERL} -p -i -e "s!YOUR-SPELUNKER-WWW-ROOT-GOES-HERE!${PROJECT}/www!" ${PROJECT}/init.d/${PROJECT_NAME}.sh
    ${PERL} -p -i -e "s!YOUR-SPELUNKER-GUNICORN-CONFIG-GOES-HERE!${PROJECT}/gunicorn/${PROJECT_NAME}.cfg!" ${PROJECT}/init.d/${PROJECT_NAME}.sh

fi

if [ ! -f /etc/init.d/${PROJECT_NAME}.sh ]
then
    sudo ln -s ${PROJECT}/init.d/${PROJECT_NAME}.sh /etc/init.d/${PROJECT_NAME}.sh
    sudo update-rc.d ${PROJECT}.sh defaults
fi

# echo "please finish writing me"
exit 1
