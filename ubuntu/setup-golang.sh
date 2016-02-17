#!/bin/sh

VERSION='1.5.3'
DIST="go${VERSION}.linux-amd64.tar.gz"

SOURCE="https://storage.googleapis.com/golang/${DIST}"

sudo apt-get remove golang

if [ ! -d /usr/local/go${VERSION} ]
then
    cd /tmp

    wget ${SOURCE}
    tar -xvzf ${DIST}

    if [ -f /tmp/${DIST} ]
    then
	rm /tmp/${DIST}
    fi

    sudo mv /tmp/go /usr/local/go${VERSION}

    if [ -L /usr/local/go ]
    then
	sudo rm /usr/local/go
    fi

    sudo ln -s /usr/local/go${VERSION} /usr/local/go

    for BIN in go gofmt godoc
    do
	
	if [ -L /usr/local/bin/${BIN} ]
	then
	    sudo rm /usr/local/bin/${BIN}
	fi

	sudo ln -s /usr/local/go/bin/${BIN} /usr/local/bin/${BIN}
    done

    cd -
fi


