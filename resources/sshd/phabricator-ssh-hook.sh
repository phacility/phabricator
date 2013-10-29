#!/bin/sh

###
###  WARNING: This feature is new and experimental. Use it at your own risk!
###

ROOT=/INSECURE/devtools/phabricator
exec "$ROOT/bin/ssh-auth" $@
