CREATE TABLE {$NAMESPACE}_metamta.sms (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  providerShortName VARCHAR(16) NOT NULL COLLATE utf8_bin,
  providerSMSID VARCHAR(40) NOT NULL COLLATE utf8_bin,
  toNumber VARCHAR(20) NOT NULL COLLATE utf8_bin,
  fromNumber VARCHAR(20) COLLATE utf8_bin,
  body LONGTEXT NOT NULL COLLATE utf8_bin,
  sendStatus VARCHAR(16) COLLATE utf8_bin,
  dateCreated INT UNSIGNED NOT NULL,
  dateModified INT UNSIGNED NOT NULL,
  UNIQUE KEY `key_provider` (providerSMSID, providerShortName)
) ENGINE=InnoDB, COLLATE utf8_general_ci;
