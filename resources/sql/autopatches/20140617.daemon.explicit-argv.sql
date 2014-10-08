ALTER TABLE {$NAMESPACE}_daemon.daemon_log
  ADD COLUMN explicitArgv longtext CHARACTER SET utf8
    COLLATE utf8_bin NOT NULL AFTER argv;
