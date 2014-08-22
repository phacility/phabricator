ALTER TABLE {$NAMESPACE}_daemon.daemon_log
  ADD COLUMN `envHash` CHAR(40) NOT NULL DEFAULT '' AFTER `dateModified`;
