#!/bin/bash

confirm() {
  echo "Press RETURN to continue, or ^C to cancel.";
  read -e ignored
}

INSTALL_URI="   https://phurl.io/u/install"

failed() {
  echo
  echo
  echo "Installation has failed."
  echo "Text above this message might be useful to understanding what exactly failed."
  echo
  echo "Please follow this guide to manually complete installation:"
  echo
  echo $INSTALL_URI
  echo
  echo "We apologize for the inconvenience."
  exit 3
}

ISSUE=`cat /etc/issue`
if [[ $ISSUE != Ubuntu* ]]
then
  echo "This script is intended for use on Ubuntu, but this system appears";
  echo "to be something else. Your results may vary.";
  echo
  confirm
fi

echo "PHABRICATOR UBUNTU INSTALL SCRIPT";
echo "This script will install Apache, Phabricator and its core dependencies.";
echo "Run it from the directory you want to install into.";
echo

echo "Testing sudo..."
sudo true
if [ $? -ne 0 ]
then
  echo "ERROR: You must be able to sudo to run this script.";
  exit 1;
fi;

echo 'Testing Ubuntu version...'

VERSION=`lsb_release -rs`
MAJOR=`expr match "$VERSION" '\([0-9]*\)'`

if [ "$MAJOR" -lt 16 ]
then
  echo 'This script is intented to install on modern operating systems; Your '
  echo 'operating system is too old for this script.'
  echo 'You can still install Phabricator manually - please consult the installation'
  echo 'guide to see how:'
  echo
  echo $INSTALL_URI
  echo
  exit 2
fi

# Ubuntu 16.04 LTS only has php 7.0 in their repos, so they need this extra ppa.
# Ubuntu 17.4 and up have official 7.2 builds.
if [ "$MAJOR" -eq 16 ]
then
  echo 'This version of Ubuntu requires additional resources in order to install'
  echo 'and run Phabricator.'
  echo 'We will now add a the following package repository to your system:'
  echo '  https://launchpad.net/~ondrej/+archive/ubuntu/php'
  echo
  echo 'This repository is generally considered safe to use.'
  confirm

  sudo add-apt-repository -y ppa:ondrej/php  || failed
fi

ROOT=`pwd`
echo "Phabricator will be installed to: ${ROOT}.";
confirm

echo "Installing dependencies: git, apache, mysql, php...";
echo
sudo apt-get -qq update
sudo apt-get install \
  git mysql-server apache2 libapache2-mod-php \
  php php-mysql php-gd php-curl php-apcu php-cli php-json php-mbstring \
  || failed

echo "Enabling mod_rewrite in Apache..."
echo
sudo a2enmod rewrite  || failed

echo "Downloading Phabricator and dependencies..."
echo
if [ ! -e libphutil ]
then
  git clone https://github.com/phacility/libphutil.git
else
  (cd libphutil && git pull --rebase)
fi

if [ ! -e arcanist ]
then
  git clone https://github.com/phacility/arcanist.git
else
  (cd arcanist && git pull --rebase)
fi

if [ ! -e phabricator ]
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
echo
echo 'Next step is "Configuring Apache webserver".'
