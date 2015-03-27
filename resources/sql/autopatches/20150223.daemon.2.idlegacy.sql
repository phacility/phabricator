UPDATE {$NAMESPACE}_daemon.daemon_log
  SET daemonID = CONCAT('legacy-', id) WHERE daemonID = '';
