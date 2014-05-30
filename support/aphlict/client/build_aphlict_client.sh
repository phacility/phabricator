#!/bin/sh

BASEDIR=`dirname $0`
ROOT=`cd $BASEDIR/../../../ && pwd`;

if [ -z "$MXMLC" ]; then
  echo "ERROR: Define environmental variable MXMLC to point to 'mxmlc' binary.";
  exit 1;
fi;

set -e

$MXMLC \
  -output=$ROOT/webroot/rsrc/swf/aphlict.swf \
  -warnings=true \
  -source-path=$ROOT/externals/vegas/src \
  -static-link-runtime-shared-libraries=true \
  $BASEDIR/src/AphlictClient.as
