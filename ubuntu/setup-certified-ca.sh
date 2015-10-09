#!/bin/sh

# ENSURE IS ROOT

DB=/usr/local/mapzen/lockedbox/certified-ca

if [ -d ${DB} ]
then
    echo "${DB} already exists"
    exit 1
fi

mkdir ${DB}
chmod 700 ${DB}
chown root ${DB}

certified-ca --bits 4096 --db ${DB} C="US" ST="CA" L="San Francisco" O="Whosonfirst" CN="Whosonfirst Boundary Issues CA"
exit 0
