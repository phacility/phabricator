CREATE TABLE {$NAMESPACE}_user.user_email (
  `id` int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  userPHID varchar(64) collate utf8_bin NOT NULL,
  address varchar(128) collate utf8_general_ci NOT NULL,
  isVerified bool not null default 0,
  isPrimary bool not null default 0,
  verificationCode varchar(64) collate utf8_bin,
  dateCreated int unsigned not null,
  dateModified int unsigned not null,
  KEY (userPHID, isPrimary),
  UNIQUE KEY (address)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
