#!/bin/sh

# this assumes that /usr/local/mapzen has already been created

if [ ! -d /usr/local/mapzen/certified]
then
    git clone https://github.com/rcrowley/certified.git /usr/local/mapzen/certified
    cd /usr/local/mapzen/certified
    sudo make install
    cd -

    certified-ca C="US" ST="CA" L="San Francisco" O="Whosonfirst" CN="Whosonfirst Boundary Issues CA"
fi

if [ ! -f /usr/local/mapzen/lockedbox/wof-boundaryissues.key ]
then

    # what to do if not EC2... (20151008/thisisaaronland)

    if [ ! -f /usr/local/mapzen/lockedbox/wof-boundaryissues-key-crt.txt ]
        PUBLIC_IP=`curl http://169.254.169.254/latest/meta-data/public-ipv4`
        sudo certified CN="localhost" +"${PUBLIC_IP}" > /usr/local/mapzen/lockedbox/wof-boundaryissues-key-crt.txt
    fi

    sudo chown root /usr/local/mapzen/lockedbox/wof-boundaryissues-key-crt.txt
    sudo chmod 600 /usr/local/mapzen/lockedbox/wof-boundaryissues-key-crt.txt

    echo "wof-boundaryissues key and cert have been generated but you still need to install them separately yourself"
fi