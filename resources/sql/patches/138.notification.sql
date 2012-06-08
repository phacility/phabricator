CREATE TABLE {$NAMESPACE}_feed.feed_storynotification (
  userPHID varchar(64) not null collate utf8_bin,
  primaryObjectPHID varchar(64) not null collate utf8_bin,
  chronologicalKey BIGINT UNSIGNED NOT NULL,
  hasViewed boolean not null,
  UNIQUE KEY (userPHID, chronologicalKey),
  KEY (userPHID, hasViewed, primaryObjectPHID)
) ENGINE=InnoDB, COLLATE utf8_general_ci;
