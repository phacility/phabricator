#!/bin/bash

confirm() {
  echo "Press RETURN to continue, or ^C to cancel.";
  read -e ignored
}

RHEL_VER_FILE="/etc/redhat-release"

if [[ ! -f $RHEL_VER_FILE ]]
then
  echo "It looks like you're not running a Red Hat-derived distribution."
  echo "This script is intended to install Phabricator on RHEL-derived"
  echo "distributions such as RHEL, Fedora, CentOS, and Scientific Linux."
  echo "Proceed with caution."
  confirm
fi

echo "PHABRICATOR RED HAT DERIVATIVE INSTALLATION SCRIPT";
echo "This script will install Phabricator and all of its core dependencies.";
echo "Run it from the directory you want to install into.";
echo

RHEL_REGEX="release ([0-9]+)\."

if [[ $(cat $RHEL_VER_FILE) =~ $RHEL_REGEX ]]
then
  RHEL_MAJOR_VER=${BASH_REMATCH[1]}
else
  echo "Ut oh, we were unable to determine your distribution's major"
  echo "version number. Please make sure you're running 6.0+ before"
  echo "proceeding."
  confirm
fi

if [[ $RHEL_MAJOR_VER < 6 && $RHEL_MAJOR_VER > 0 ]]
then
  echo "** WARNING **"
  echo "A major version less than 6 was detected. Because of this,"
  echo "several needed dependencies are not available via default repos."
  echo "Specifically, RHEL 5 does not have a PEAR package for php53-*."
  echo "We will attempt to install it manually, for APC. Please be careful."
  confirm
fi

echo "Phabricator will be installed to: $(pwd).";
confirm

echo "Testing sudo/root..."
if [[ $EUID -ne 0 ]] # Check if we're root. If we are, continue.
then
  sudo true
  SUDO="sudo"
  if [[ $? -ne 0 ]]
  then
    echo "ERROR: You must be able to sudo to run this script, or run it as root.";
    exit 1
  fi

fi

if [[ $RHEL_MAJOR_VER == 5 ]]
then
  # RHEL 5's "php" package is actually 5.1. The "php53" package won't let us install php-pecl-apc.
  # (it tries to pull in php 5.1 stuff) ...
  yum repolist | grep -i epel
  if [ $? -ne 0 ]; then
    echo "It doesn't look like you have the EPEL repo enabled. We are to add it"
    echo "for you, so that we can install git."
    $SUDO rpm -Uvh http://download.fedoraproject.org/pub/epel/5/i386/epel-release-5-4.noarch.rpm
  fi
  YUMCOMMAND="$SUDO yum install httpd git php53 php53-cli php53-mysql php53-process php53-devel php53-gd gcc wget make pcre-devel mysql-server"
else
  # RHEL 6+ defaults with php 5.3
  YUMCOMMAND="$SUDO yum install httpd git php php-cli php-mysql php-process php-devel php-gd php-pecl-apc php-pecl-json php-mbstring mysql-server"
fi

echo "Dropping to yum to install dependencies..."
echo "Running: ${YUMCOMMAND}"
echo "Yum will prompt you with [Y/n] to continue installing."

$YUMCOMMAND

if [[ $? -ne 0 ]]
then
  echo "The yum command failed. Please fix the errors and re-run this script."
  exit 1
fi

if [[ $RHEL_MAJOR_VER == 5 ]]
then
  # Now that we've ensured all the devel packages required for pecl/apc are there, let's
  # set up PEAR, and install apc.
  echo "Attempting to install PEAR"
  wget http://pear.php.net/go-pear.phar
  $SUDO php go-pear.phar && $SUDO pecl install apc
fi

if [[ $? -ne 0 ]]
then
  echo "The apc install failed. Continuing without APC, performance may be impacted."
fi

pidof httpd 2>&1 > /dev/null
if [[ $? -eq 0 ]]
then
  echo "If php was installed above, please run: /etc/init.d/httpd graceful"
else
  echo "Please remember to start the httpd with: /etc/init.d/httpd start"
fi

pidof mysqld 2>&1 > /dev/null
if [[ $? -ne 0 ]]
then
  echo "Please remember to start the mysql server: /etc/init.d/mysqld start"
fi

confirm

if [[ ! -e libphutil ]]
then
  git clone https://github.com/phacility/libphutil.git
else
  (cd libphutil && git pull --rebase)
fi

if [[ ! -e arcanist ]]
then
  git clone https://github.com/phacility/arcanist.git
else
  (cd arcanist && git pull --rebase)
fi

if [[ ! -e phabricator ]]
then
  git clone https://github.com/phacility/phabricator.git
else
  (cd phabricator && git pull --rebase)
fi

echo
echo
echo "Install probably worked mostly correctly. Continue with the 'Configuration Guide':";
echo
echo "    https://secure.phabricator.com/book/phabricator/article/configuration_guide/";
