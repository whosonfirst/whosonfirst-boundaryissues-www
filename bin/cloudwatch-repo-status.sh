#!/bin/sh

WHOAMI=`python -c 'import os, sys; print os.path.realpath(sys.argv[1])' $0`
BIN=`dirname $WHOAMI`
AWS=/usr/bin/aws
export AWS_DEFAULT_REGION='us-east-1'

VALUE=`/usr/bin/php $BIN/repo_status.php -s`
TIMESTAMP=`date -Iseconds | sed -e 's/+0000/.000Z/'` # format: 2017-09-18T21:00:57.000Z

$AWS cloudwatch put-metric-data --metric-name RepoBusySeconds --namespace WOF --value $VALUE --timestamp $TIMESTAMP
