#!/bin/sh

PYTHON=`which python`
WHOAMI=`${PYTHON} -c 'import os, sys; print os.path.realpath(sys.argv[1])' $0`

PARENT=`dirname $WHOAMI`
PROJECT=`dirname $PARENT`

# because apt installs git 1.9.x

# echo "Y U RUN THIS AS SCRIPT NOW"
# exit

sudo apt-get install -y build-essential libcurl4-openssl-dev libssl-dev tcl-dev gettext asciidoc

git clone git@github.com:git/git.git
cd git/
make configure

sudo apt-get install -y autoconf
make configure
./configure
make all doc

sudo apt-get remove -y git
sudo make install install-doc install-html

cd -

exit 0
