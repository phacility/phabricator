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

    $mode_string = queryfx_one($conn_raw, 'SELECT @@sql_mode');
    $modes = explode(',', $mode_string['@@sql_mode']);
    if (!in_array('STRICT_ALL_TABLES', $modes)) {
      $summary = pht(
        'MySQL is not in strict mode, but using strict mode is strongly '.
        'encouraged.');

      $message = pht(
        "On your MySQL instance, the global %s is not set to %s. ".
        "It is strongly encouraged that you enable this mode when running ".
        "Phabricator.\n\n".
        "By default MySQL will silently ignore some types of errors, which ".
        "can cause data loss and raise security concerns. Enabling strict ".
        "mode makes MySQL raise an explicit error instead, and prevents this ".
        "entire class of problems from doing any damage.\n\n".
        "You can find more information about this mode (and how to configure ".
        "it) in the MySQL manual. Usually, it is sufficient to add this to ".
        "your %s file (in the %s section) and then restart %s:\n\n".
        "%s\n".
        "(Note that if you run other applications against the same database, ".
        "they may not work in strict mode. Be careful about enabling it in ".
        "these cases.)",
        phutil_tag('tt', array(), 'sql_mode'),
        phutil_tag('tt', array(), 'STRICT_ALL_TABLES'),
        phutil_tag('tt', array(), 'my.cnf'),
        phutil_tag('tt', array(), '[mysqld]'),
        phutil_tag('tt', array(), 'mysqld'),
        phutil_tag('pre', array(), 'sql_mode=STRICT_ALL_TABLES'));

      $this->newIssue('mysql.mode')
        ->setName(pht('MySQL STRICT_ALL_TABLES Mode Not Set'))
        ->setSummary($summary)
        ->setMessage($message);
    }
  }

}
