ALTER TABLE {$NAMESPACE}_daemon.daemon_log
  ADD UNIQUE KEY `key_daemonID` (daemonID);
