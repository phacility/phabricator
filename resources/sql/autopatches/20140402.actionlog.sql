CREATE TABLE {$NAMESPACE}_system.system_actionlog (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  actorHash CHAR(12) NOT NULL COLLATE latin1_bin,
  actorIdentity VARCHAR(255) NOT NULL COLLATE utf8_bin,
  action CHAR(32) NOT NULL COLLATE utf8_bin,
  score DOUBLE NOT NULL,
  epoch INT UNSIGNED NOT NULL,

  KEY `key_epoch` (epoch),
  KEY `key_action` (actorHash, action, epoch)

) ENGINE=InnoDB, COLLATE utf8_general_ci;
