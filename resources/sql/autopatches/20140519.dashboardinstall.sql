CREATE TABLE {$NAMESPACE}_dashboard.dashboard_install (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  installerPHID VARCHAR(64) NOT NULL COLLATE utf8_bin,
  objectPHID VARCHAR(64) NOT NULL COLLATE utf8_bin,
  applicationClass VARCHAR(64) NOT NULL COLLATE utf8_bin,
  dashboardPHID VARCHAR(64) NOT NULL COLLATE utf8_bin,
  dateCreated INT UNSIGNED NOT NULL,
  dateModified INT UNSIGNED NOT NULL,
  UNIQUE KEY (objectPHID, applicationClass)
) ENGINE=InnoDB, COLLATE utf8_general_ci;
