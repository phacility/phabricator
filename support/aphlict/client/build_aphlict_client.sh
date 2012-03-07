#!/bin/sh

BASEDIR=`dirname $0`
ROOT=`cd $BASEDIR/../../../ && pwd`;

if [ -z "$MXMLC" ]; then
  echo "ERROR: Define environmental variable MXMLC to point to 'mxmlc' binary.";
  exit 1;
fi;

set -e
set -x

# cp -R $ROOT/externals/vegas/src $BASEDIR/src/vegas

(cd $BASEDIR && $MXMLC \
  -output aphlict.swf \
  -default-background-color=0x444444 \
  -default-size=500,500 \
  -warnings=true \
  -debug=true \
  -source-path=$ROOT/externals/vegas/src \
  -static-link-runtime-shared-libraries=true \
  src/Aphlict.as)

mv $BASEDIR/aphlict.swf $ROOT/webroot/rsrc/swf/aphlict.swf

#  -target-player=10.2.0 \
