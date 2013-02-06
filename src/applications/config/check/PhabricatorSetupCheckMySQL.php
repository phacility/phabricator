<?php

final class PhabricatorSetupCheckMySQL extends PhabricatorSetupCheck {

  protected function executeChecks() {
    $conn_raw = id(new PhabricatorUser())->establishConnection('w');

    $max_allowed_packet = queryfx_one(
      $conn_raw,
      'SHOW VARIABLES LIKE %s',
      'max_allowed_packet');
    $max_allowed_packet = idx($max_allowed_packet, 'Value', PHP_INT_MAX);

    $recommended_minimum = 1024 * 1024;
    if ($max_allowed_packet < $recommended_minimum) {
      $message = pht(
        "MySQL is configured with a very small 'max_allowed_packet' (%d), ".
        "which may cause some large writes to fail. Strongly consider raising ".
        "this to at least %d in your MySQL configuration.",
        $max_allowed_packet,
        $recommended_minimum);

      $this->newIssue('mysql.max_allowed_packet')
        ->setName(pht('Small MySQL "max_allowed_packet"'))
        ->setMessage($message);
    }

    if (PhabricatorEnv::getEnvConfig('phabricator.developer-mode')) {
      $mode_string = queryfx_one($conn_raw, "SELECT @@sql_mode");
      $modes = explode(',', $mode_string['@@sql_mode']);
      if (!in_array('STRICT_ALL_TABLES', $modes)) {
        $summary = pht(
          "MySQL is not in strict mode, but should be for Phabricator ".
          "development.");

        $message = pht(
          "This install is in developer mode, but the global sql_mode is not ".
          "set to 'STRICT_ALL_TABLES'. It is recommended that you set this ".
          "mode while developing Phabricator. Strict mode will promote some ".
          "query warnings to errors, and ensure you don't miss them during ".
          "development. You can find more information about this mode (and ".
          "how to configure it) in the MySQL manual.");

        $this->newIssue('mysql.mode')
          ->setName(pht('MySQL STRICT_ALL_TABLES Mode Not Set'))
          ->addPhabricatorConfig('phabricator.developer-mode')
          ->setSummary($summary)
          ->setMessage($message);
      }
    }

  }

}
