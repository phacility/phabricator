#!/bin/bash

confirm() {
  echo "Press RETURN to continue, or ^C to cancel.";
  read -e ignored
}

GIT='git'

LTS="Ubuntu 10.04"
ISSUE=`cat /etc/issue`
if [[ $ISSUE != Ubuntu* ]]
then
  echo "This script is intended for use on Ubuntu, but this system appears";
  echo "to be something else. Your results may vary.";
  echo
  confirm
elif [[ `expr match "$ISSUE" "$LTS"` -eq ${#LTS} ]]
then
  GIT='git-core'
fi

echo "PHABRICATOR UBUNTU INSTALL SCRIPT";
echo "This script will install Phabricator and all of its core dependencies.";
echo "Run it from the directory you want to install into.";
echo

ROOT=`pwd`
echo "Phabricator will be installed to: ${ROOT}.";
confirm

echo "Testing sudo..."
sudo true
if [ $? -ne 0 ]
then
  echo "ERROR: You must be able to sudo to run this script.";
  exit 1;
fi;

echo "Installing dependencies: git, apache, mysql, php...";
echo

set +x

sudo apt-get -qq update
sudo apt-get install $GIT mysql-server apache2 php5 php5-mysql php5-gd php5-dev php5-curl php-apc php5-cli dpkg-dev

# Enable mod_rewrite
sudo a2enmod rewrite

HAVEPCNTL=`php -r "echo extension_loaded('pcntl');"`
if [ $HAVEPCNTL != "1" ]
then
  echo "Installing pcntl...";
  echo
  apt-get source php5
  PHP5=`ls -1F | grep '^php5-.*/$'`
  (cd $PHP5/ext/pcntl && phpize && ./configure && make && sudo make install)
else
  echo "pcntl already installed";
fi

if [ ! -e libphutil ]
then
  git clone git://github.com/facebook/libphutil.git
else
  (cd libphutil && git pull --rebase)
fi

if [ ! -e arcanist ]
then
  git clone git://github.com/facebook/arcanist.git
else
  (cd arcanist && git pull --rebase)
fi

if [ ! -e phabricator ]
then
  git clone git://github.com/facebook/phabricator.git
else
  (cd phabricator && git pull --rebase)
fi

echo
echo
echo "Install probably worked mostly correctly. Continue with the 'Configuration Guide':";
echo
echo "    http://www.phabricator.com/docs/phabricator/article/Configuration_Guide.html";
echo
echo "You can delete any php5-* stuff that's left over in this directory if you want.";
