#!/bin/sh

PYTHON=`which python`
WHOAMI=`${PYTHON} -c 'import os, sys; print os.path.realpath(sys.argv[1])' $0`

PARENT=`dirname $WHOAMI`
PROJECT=`dirname $PARENT`

sudo ${PARENT}/setup-git-lfs-deb.sh

sudo apt-get install -y git-lfs

# Y U NO WORK WITH git 2.8.0 ????
# git install lfs

exit 0
