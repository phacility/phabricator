/* Extend from 32 characters to 128. */

ALTER TABLE {$NAMESPACE}_user.user
  CHANGE passwordHash passwordHash VARCHAR(128) COLLATE utf8_bin;
