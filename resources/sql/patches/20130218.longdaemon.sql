ALTER TABLE {$NAMESPACE}_daemon.daemon_log
  CHANGE argv argv LONGTEXT NOT NULL COLLATE utf8_bin;
