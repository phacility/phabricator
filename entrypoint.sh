#!/bin/sh
# This Source Code Form is subject to the terms of the Mozilla Public
# License, v. 2.0. If a copy of the MPL was not distributed with this
# file, You can obtain one at http://mozilla.org/MPL/2.0/.
#
# This Source Code Form is "Incompatible With Secondary Licenses", as
# defined by the Mozilla Public License, v. 2.0.

# Configure Phabricator on startup from environment variables.

set -ex

# Our base Docker image (php:7.3.11-fpm-alpine) uses SIGQUIT as the stop signal, which sh will ignore (see 12.1.1.2 in
# this article: https://linux.die.net/Bash-Beginners-Guide/sect_12_01.html).
# > In the absence of any traps, an interactive Bash shell ignores SIGTERM and SIGQUIT.
# This is handled in two ways:
# 1. This trap will be executed when sh receives a SIGTERM and bash isn't waiting for a command to complete
# 2. For the long-running process (php-fpm), "exec" it so that it receives the SIGTERM (rather than sh, which is
#    waiting for php-fpm to complete)
trap 'exit 0' 3

if test -e /app/tmpfiles/local.json; then
    cp /app/tmpfiles/local.json /app/phabricator/conf/local/local.json
fi

cd phabricator

test -n "${MYSQL_HOST}" \
  && /app/wait-for-mysql.php \
  && ./bin/config set mysql.host ${MYSQL_HOST}
test -n "${MYSQL_PORT}" \
  && ./bin/config set mysql.port ${MYSQL_PORT}
test -n "${MYSQL_USER}" \
  && ./bin/config set mysql.user ${MYSQL_USER}
set +x
test -n "${MYSQL_USER}" \
  && ./bin/config set mysql.pass ${MYSQL_PASS}
set -x
test -n "${1}" \
  && ARG=$(echo ${1:-start}  | tr [A-Z] [a-z])

start() {
    test -n "$1" && ARG="$1"
    set +e

    # Set the local repository
    if [ -n "${REPOSITORY_LOCAL_PATH}" ]; then
        if [ ! -d "${REPOSITORY_LOCAL_PATH}" ]; then
            mkdir -p "${REPOSITORY_LOCAL_PATH}"
        fi
        ./bin/config set repository.default-local-path "${REPOSITORY_LOCAL_PATH}"
        else
        echo "No REPOSITORY_LOCAL_PATH set"
        exit
    fi

    # You should set the base URI to the URI you will use to access Phabricator,
    # like "http://phabricator.example.com/".

    # Include the protocol (http or https), domain name, and port number if you are
    # using a port other than 80 (http) or 443 (https).
    test -n "${PHABRICATOR_URI}" \
        && ./bin/config set phabricator.base-uri "${PHABRICATOR_URI}"
    test -n "${PHABRICATOR_CDN_URI}" \
        && ./bin/config set security.alternate-file-domain "${PHABRICATOR_CDN_URI}"

    # When running as a development environment or for demonstration purposes we
    # may want to set the default values for bugzilla settings to something custom.
    test -n "${BUGZILLA_URL}" \
        && ./bin/config set bugzilla.url "${BUGZILLA_URL}"
    test -n "${BUGZILLA_AUTOMATION_USER}" \
        && ./bin/config set bugzilla.automation_user "${BUGZILLA_AUTOMATION_USER}"
    test -n "${BUGZILLA_AUTOMATION_API_KEY}" \
        && ./bin/config set bugzilla.automation_api_key "${BUGZILLA_AUTOMATION_API_KEY}"


    # Set recommended runtime configuration values to silence setup warnings.
    ./bin/config set pygments.enabled true
    ./bin/config set phabricator.timezone UTC

    # Ensure that we have an updated static resources map
    # Required so extension resources are accounted for and available
    ./bin/celerity map
    case "$ARG" in
      "php-fpm")
        /usr/local/sbin/php-fpm -F
        ;;
      "dev")
        # the host is always octet 1 on the current virtual network
        # E.g.: if this container is running on 172.27.0.16, the host is 172.27.0.1
        host_address=$(hostname -i | awk '{split($1,a,".");print a[1] "." a[2] "." a[3] ".1"}')
        export XDEBUG_CONFIG="remote_host=$host_address remote_enable=1"
        export PHP_IDE_CONFIG="serverName=phabricator.test"
        ./bin/phd start && exec /usr/local/sbin/php-fpm -F
        ;;
      *)
        ./bin/phd start && exec /usr/local/sbin/php-fpm -F
        ;;
    esac
}

check_database() {
    # Upgrade database and also create one if it does not exist
    set +e
    DO_DATABASE=0
    ./bin/storage status > /dev/null 2>&1
    [ $? -gt 0 ] && DO_DATABASE=1
    [ ! -z "$(./bin/storage status | grep -i 'not applied')" ] && DO_DATABASE=1
    [ $DO_DATABASE -gt 0 ] && ./bin/storage upgrade --force
}

case "$ARG" in
  "dev_start")
      set +e
      check_database
      ./bin/config set auth.require-approval false
      ./bin/config set bugzilla.require_mfa false
      # Default to Mozilla's implementation for submitting emails.
      ./bin/config set email.default 2
      ./bin/config set phabricator.show-prototypes true
      ./bin/config set storage.mysql-engine.max-size 8388608
      start dev
      ;;
  "start")
      start
      ;;
  "docs")
      # Build diviner docs
      ./bin/diviner generate
      ;;
  "data")
      # Allows the container to be used as a data-volume only container
      /bin/true && exit
      ;;
  "php-fpm-only")
      start php-fpm
      ;;
  "shell"|"admin")
      /bin/sh
      ;;
  "dump")
      ./bin/storage dump
      exit
      ;;
  "check_database")
      check_database
      exit
      ;;
  "arc_liberate")
	    cd /app/moz-extensions/
	    /app/arcanist/bin/arc liberate src/
	    ;;
  "test_phab")
	    # Find all extension tests and call them
	    cd /app
	    /app/arcanist/bin/arc unit /app/moz-extensions/src/*/*/__tests__/*php
	    ;;
  *)
      exec $ARG
      ;;
esac
