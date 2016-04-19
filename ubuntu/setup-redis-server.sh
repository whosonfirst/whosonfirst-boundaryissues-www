#!/bin/sh

if [ ! -d /usr/local/mapzen/flamework-redis ]
then
    git clone https://github.com/whosonfirst/flamework-redis.git /usr/local/mapzen/flamework-redis
fi

cd /usr/local/mapzen/flamework-redis
git pull origin master

./ubuntu/setup-redis-server.sh

cd -
exit 1
