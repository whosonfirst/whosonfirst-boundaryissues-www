#!/bin/sh

# ENSURE IS ROOT

DB=/usr/local/mapzen/lockedbox/certified-ca

if [ -f /usr/local/mapzen/lockedbox/wof-boundaryissues.key ]
then
    echo "boundary issues key already exists"
    exit 1
fi

if [ -f /usr/local/mapzen/lockedbox/wof-boundaryissues-key-crt.txt ]
then
    echo "boundary issues cert already exists"
    exit 1
fi


# PUBLIC_IP=`curl http://169.254.169.254/latest/meta-data/public-ipv4`
PUBLIC_IP='127.0.0.1'

certified --bits 4096 --db ${DB} CN="localhost" +"${PUBLIC_IP}" > /usr/local/mapzen/lockedbox/wof-boundaryissues-key-crt.txt

chown root /usr/local/mapzen/lockedbox/wof-boundaryissues-key-crt.txt
chmod 600 /usr/local/mapzen/lockedbox/wof-boundaryissues-key-crt.txt

echo "wof-boundaryissues key and cert have been generated but you still need to install them separately yourself"
