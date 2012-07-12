#! /usr/bin/env sh

confirm() {
  echo "Press RETURN to continue, or ^C to cancel.";
  read -e ignored
}

ALAMI_VER_FILE="/etc/image-id"

if [[ ! -f $ALAMI_VER_FILE ]]
then
  echo "It looks like you're not running on the Amazon Linux AMI."
  echo "Proceed with caution."
  confirm
fi

echo "PHABRICATOR AMAZON LINUX AMI INSTALLATION SCRIPT";
echo "This script will install Phabricator and all of its core dependencies.";
echo "Run it from the directory you want to install into.";
echo

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

YUMCOMMAND="$SUDO yum install httpd git-core git-extras php php-cli php-mysql php-process php-devel php-gd php-pecl-apc mysql-server"

echo "Dropping to yum to install dependencies..."
echo "Running: ${YUMCOMMAND}"
echo "Yum will prompt you with [Y/n] to continue installing."

$YUMCOMMAND

if [[ $? -ne 0 ]]
then
  echo "The yum command failed. Please fix the errors and re-run this script."
  exit 1
fi

if [[ ! -e libphutil ]]
then
  git clone git://github.com/facebook/libphutil.git
else
  (cd libphutil && git pull --rebase)
fi

if [[ ! -e arcanist ]]
then
  git clone git://github.com/facebook/arcanist.git
else
  (cd arcanist && git pull --rebase)
fi

if [[ ! -e phabricator ]]
then
  git clone git://github.com/facebook/phabricator.git
else
  (cd phabricator && git pull --rebase)
fi

(cd phabricator && git submodule update --init)

if [[ "$(pidof httpd)" ]]
then
  echo "If PHP was installed above, please run: /etc/init.d/httpd graceful"
else
  echo "Please remember to start the httpd with: /etc/init.d/httpd start"
fi

if [[ ! "$(pidof mysql)" ]]
then
  echo "Please remember to start the mysql server: /etc/init.d/mysqld start"
fi

echo
echo
echo "Install probably worked mostly correctly. Continue with the 'Configuration Guide':";
echo
echo "    http://www.phabricator.com/docs/phabricator/article/Configuration_Guide.html";
echo
