#!/bin/sh

if [ ! -d /usr/local/mapzen/flamework-redis ]
then
    git clone https://github.com/whosonfirst/flamework-redis.git /usr/local/mapzen/flamework-redis
fi

cd /usr/local/mapzen/flamework-redis
git pull origin master

./ubuntu/setup-redis-server.sh

cd -

if [ ! -d /usr/local/mapzen/redis-tools ]
then
    git clone https://github.com/whosonfirst/redis-tools.git /usr/local/mapzen/redis-tools
fi 

cd /usr/local/mapzen/redis-tools
git pull origin master

cd -

sudo easy_install redis

exit 1
