ALTER TABLE {$NAMESPACE}_daemon.daemon_log
  DROP COLUMN envHash;

ALTER TABLE {$NAMESPACE}_daemon.daemon_log
  DROP COLUMN envInfo;
