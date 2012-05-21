CREATE TABLE {$NAMESPACE}_harbormaster.harbormaster_scratchtable (
  `id` int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `data` varchar(64) NOT NULL collate utf8_bin,
  `dateCreated` int unsigned NOT NULL,
  `dateModified` int unsigned NOT NULL,
  KEY (data)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
