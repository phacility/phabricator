#!/bin/bash

confirm() {
  echo "Press RETURN to continue, or ^C to cancel.";
  read -e ignored
}

INSTALL_URI="https://phurl.io/u/install"

failed() {
  echo -e "\n \n"
  echo "Installation has failed."
  echo -e "Text above this message might be useful to understanding what exactly failed. \n"
  echo -e "Please follow this guide to manually complete installation: \n"
  echo -e "$INSTALL_URI \n"
  echo "We apologize for the inconvenience."
  exit 3
}

ubuntuinfo=$(grep PRETTY_NAME /etc/os-release | cut -d= -f2)

if [[ $ubuntuinfo != Ubuntu* ]]
then
  echo "This script is intended for use on Ubuntu, but this system appears";
  echo -e "to be something else. Your results may vary. \n";
  confirm
fi

echo "PHABRICATOR UBUNTU INSTALL SCRIPT";
echo "This script will install Apache, Phabricator and its core dependencies.";
echo -e "Run it from the directory you want to install into. \n";

echo "Testing sudo..."
sudo true
if [ $? -ne 0 ]
then
  echo "ERROR: You must be able to sudo to run this script.";
  exit 1;
fi;

echo 'Testing Ubuntu version...'


MAJOR=$(echo ubuntuinfo | awk '{print $2}' | cut -d. -f1)

if [ "$MAJOR" -lt 16 ]
then
  echo 'This script is intented to install on modern operating systems; Your '
  echo 'operating system is too old for this script.'
  echo 'You can still install Phabricator manually - please consult the installation'
  echo -e "guide to see how: \n"
  echo -e "$INSTALL_URI \n"
  exit 2
fi

# Ubuntu 16.04 LTS only has php 7.0 in their repos, so they need this extra ppa.
# Ubuntu 17.4 and up have official 7.2 builds.
if [ "$MAJOR" -eq 16 ]
then
  echo 'This version of Ubuntu requires additional resources in order to install'
  echo 'and run Phabricator.'
  echo 'We will now add a the following package repository to your system:'
  echo -e "  https://launchpad.net/~ondrej/+archive/ubuntu/php \n "
  echo 'This repository is generally considered safe to use.'
  confirm
  sudo add-apt-repository -y ppa:ondrej/php  || failed
fi

ROOT=$(pwd)
echo "Phabricator will be installed to: ${ROOT}.";
confirm

echo -e "Installing dependencies: git, apache, mysql, php... \n";
sudo apt-get -qq update
sudo apt-get install \
  git mysql-server apache2 libapache2-mod-php \
  php php-mysql php-gd php-curl php-apcu php-cli php-json php-mbstring \
  || failed

echo -e "Enabling mod_rewrite in Apache... \n"
sudo a2enmod rewrite  || failed

echo -e "Downloading Phabricator and dependencies... \n"

for software in "libphutil arcanist phabricator"; do
if [ ! -e "$software" ]
then
  git clone https://github.com/phacility/"$software".git
else
  (cd "$software" && git pull --rebase)
fi
done

echo -e "\n \n"
echo -e "Install probably worked mostly correctly. Continue with the 'Configuration Guide': \n";
echo -e "    https://secure.phabricator.com/book/phabricator/article/configuration_guide/ \n";
echo 'Next step is "Configuring Apache webserver".'
