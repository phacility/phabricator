#/bin/bash

if [ ! -f /var/lib/mysql/ibdata1 ]; then

	echo "init Mysql admin user"
	killall mysqld

	mysql_install_db

	mysqld_safe &
	sleep 10s

	echo "GRANT ALL ON *.* TO admin@'%' IDENTIFIED BY 'admin' WITH GRANT OPTION; FLUSH PRIVILEGES" | mysql
fi

cd /opt/libphutil && git pull
cd /opt/arcanist && git pull
cd /opt/phabricator && git pull

mkdir -p /opt/phabricator/conf/local
touch /opt/phabricator/conf/local/local.json

chmod 666 /opt/phabricator/conf/local/local.json

# if container restart, fix mysql rights
chown -R mysql:mysql /var/lib/docker

cd /opt/phabricator && ./bin/storage upgrade --force
cd /opt/phabricator && ./bin/phd restart
