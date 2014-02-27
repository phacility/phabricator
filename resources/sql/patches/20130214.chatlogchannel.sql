CREATE TABLE {$NAMESPACE}_chatlog.chatlog_channel (
  id INT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
  serviceName VARCHAR(64) COLLATE utf8_bin NOT NULL,
  serviceType VARCHAR(32) COLLATE utf8_bin NOT NULL,
  channelName VARCHAR(64) COLLATE utf8_bin NOT NULL,
  viewPolicy VARCHAR(64) COLLATE utf8_bin NOT NULL,
  editPolicy VARCHAR(64) COLLATE utf8_bin NOT NULL,
  dateCreated INT UNSIGNED NOT NULL,
  dateModified INT UNSIGNED NOT NULL,
  UNIQUE KEY `key_channel` (channelName, serviceType, serviceName)
)ENGINE=InnoDB DEFAULT CHARSET=utf8;
