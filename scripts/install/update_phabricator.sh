#!/bin/sh

set -e
set -x

# This is an example script for updating Phabricator, similar to the one used to
# update <https://secure.phabricator.com/>. It might not work perfectly on your
# system, but hopefully it should be easy to adapt.

# NOTE: This script assumes you are running it from a directory which contains
# arcanist/, libphutil/, phabricator/, and possibly diviner/.

ROOT=`pwd` # You can hard-code the path here instead.

### UPDATE WORKING COPIES ######################################################

if [ -e $ROOT/diviner ]
then
  cd $ROOT/diviner
  git pull
fi

cd $ROOT/libphutil
git pull

cd $ROOT/arcanist
git pull

cd $ROOT/phabricator
git pull


### RUN TESTS ##################################################################

# This is an acceptance test that makes sure all symbols can be loaded to
# avoid issues like missing methods in descendants of abstract base classes.
cd $ROOT/phabricator
../arcanist/bin/arc unit src/infrastructure/__tests__/


### DETECT OS AND SET APACHE START/STOP COMMANDS ##################################

lowercase(){
    echo "$1" | sed "y/ABCDEFGHIJKLMNOPQRSTUVWXYZ/abcdefghijklmnopqrstuvwxyz/"
}

OS=`lowercase \`uname\``
KERNEL=`uname -r`
MACH=`uname -m`
HTTPD_START_COMMAND="sudo /etc/init.d/httpd start"
HTTPD_STOP_COMMAND="sudo /etc/init.d/httpd stop"

if [ "{$OS}" == "darwin" ]; then
    #OS=mac
    HTTPD_START_COMMAND="sudo /usr/sbin/apachectl -k start"
    HTTPD_STOP_COMMAND="sudo /usr/sbin/apachectl -k stop"
else
    OS=`uname`
    if [ "${OS}" = "Linux" ] ; then
        if [ -f /etc/redhat-release ] ; then
            HTTPD_START_COMMAND="sudo /etc/init.d/httpd start"
            HTTPD_STOP_COMMAND="sudo /etc/init.d/httpd  stop"
        elif [ -f /etc/mandrake-release ] ; then
            HTTPD_START_COMMAND="sudo /etc/init.d/httpd start"
            HTTPD_STOP_COMMAND="sudo /etc/init.d/httpd  stop"
        elif [ -f /etc/debian_version ] ; then
            HTTPD_START_COMMAND="sudo /etc/init.d/apache2 start"
            HTTPD_STOP_COMMAND="sudo /etc/init.d/apache2  stop"
        elif [ -f /etc/debian_version ] ; then
            HTTPD_START_COMMAND="sudo /etc/init.d/apache2 start"
            HTTPD_STOP_COMMAND="sudo /etc/init.d/apache2  stop"
        fi
    fi
fi

### CYCLE APACHE AND DAEMONS ###################################################

# Stop daemons.
$ROOT/phabricator/bin/phd stop

# Stop Apache. Depening on what system you're running, you may need to use
# 'apachectl' or something else to cycle apache.
$HTTPD_STOP_COMMAND

# Upgrade the database schema.
$ROOT/phabricator/bin/storage upgrade --force

# Restart apache.
$HTTPD_START_COMMAND

# Restart daemons. Customize this to start whatever daemons you're running on
# your system.

$ROOT/phabricator/bin/phd start
# $ROOT/phabricator/bin/phd launch ircbot /config/bot.json


### GENERATE DOCUMENTATION #####################################################

# This generates documentation if you have diviner/ checked out. You generally
# don't need to do this unless you're contributing to Phabricator and want to
# preview some of the amazing documentation you've just written.
if [ -e $ROOT/diviner ]
then
  cd $ROOT/diviner && $ROOT/diviner/bin/diviner .
  cd $ROOT/libphutil && $ROOT/diviner/bin/diviner .
  cd $ROOT/arcanist && $ROOT/diviner/bin/diviner .
  cd $ROOT/phabricator && $ROOT/diviner/bin/diviner .
fi
