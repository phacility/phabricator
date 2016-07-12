CREATE TABLE {$NAMESPACE}_user.user_nametoken (
  token VARCHAR(255) NOT NULL,
  userID INT UNSIGNED NOT NULL,
  KEY (token(128)),
  key (userID)
) ENGINE=InnoDB;
