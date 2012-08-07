ALTER TABLE {$NAMESPACE}_daemon.daemon_log
  ADD COLUMN `status` varchar(8) NOT NULL;

UPDATE {$NAMESPACE}_daemon.daemon_log SET `status`='exit';
