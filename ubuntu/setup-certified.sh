#!/bin/sh

WHOAMI=`python -c 'import os, sys; print os.path.realpath(sys.argv[1])' $0`

UBUNTU=`dirname $WHOAMI`
ROOT=`dirname $UBUNTU`

sudo apt-get update
sudo apt-get -y upgrade

# RDISCOUNT=`gem list | grep rdiscount | wc -l`
# guh.... dunno why this is necessary but it seems to be
# (20160226/thisisaaronland)

sudo gem install rdiscount

if [ ! -d ${ROOT}/certified ]
then

    sudo apt-get install -y ruby-ronn

    git clone https://github.com/rcrowley/certified.git ${ROOT}/certified
    cd ${ROOT}/certified

    sudo make install
    cd -
fi

TEST=`grep certified ${ROOT}/.gitignore | wc -l`

if [ ${TEST} = 0 ]
then
    echo "certified" >> ${ROOT}/.gitignore
fi
