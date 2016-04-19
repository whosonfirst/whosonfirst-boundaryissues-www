#!/bin/sh

if [ ! -d /usr/local/mapzen/flamework-logstash ]
then
    git clone https://github.com/whosonfirst/flamework-logstash.git
fi

cd /usr/local/mapzen/flamework-logstash
git pull origin master

./ubuntu/setup-logstash.sh

cd -
exit 1
