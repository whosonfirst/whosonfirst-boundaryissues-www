#!/bin/sh

if [ ! -d /usr/local/mapzen/flamework-gearman ]
then
    git clone https://github.com/whosonfirst/flamework-gearman.git /usr/local/mapzen/flamework-gearman
fi

cd /usr/local/mapzen/flamework-gearman
./ubuntu/setup-gearmand.sh

cd -
