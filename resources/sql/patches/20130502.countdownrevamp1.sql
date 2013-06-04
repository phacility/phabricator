ALTER TABLE {$NAMESPACE}_countdown.countdown_timer
  RENAME TO {$NAMESPACE}_countdown.countdown;

ALTER TABLE {$NAMESPACE}_countdown.countdown
  change datepoint epoch INT UNSIGNED NOT NULL;

ALTER TABLE {$NAMESPACE}_countdown.countdown
  ADD COLUMN phid VARCHAR(64) NOT NULL COLLATE utf8_bin AFTER id;
