CREATE TABLE {$NAMESPACE}_drydock.drydock_log (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  resourceID INT UNSIGNED,
  leaseID INT UNSIGNED,
  epoch INT UNSIGNED NOT NULL,
  message LONGTEXT COLLATE utf8_general_ci NOT NULL,
  KEY (resourceID, epoch),
  KEY (leaseID, epoch),
  KEY (epoch)
) ENGINE=InnoDB, COLLATE utf8_general_ci;
