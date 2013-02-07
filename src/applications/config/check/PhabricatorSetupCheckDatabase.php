<?php

final class PhabricatorSetupCheckDatabase extends PhabricatorSetupCheck {

  public function getExecutionOrder() {
    // This must run after basic PHP checks, but before most other checks.
    return 0.5;
  }

  protected function executeChecks() {
    $conf = PhabricatorEnv::newObjectFromConfig('mysql.configuration-provider');
    $conn_user = $conf->getUser();
    $conn_pass = $conf->getPassword();
    $conn_host = $conf->getHost();

    ini_set('mysql.connect_timeout', 2);

    $conn_raw = PhabricatorEnv::newObjectFromConfig(
      'mysql.implementation',
      array(
        array(
          'user'      => $conn_user,
          'pass'      => $conn_pass,
          'host'      => $conn_host,
          'database'  => null,
        ),
      ));

    try {
      queryfx($conn_raw, 'SELECT 1');
    } catch (AphrontQueryConnectionException $ex) {
      $message = pht(
        "Unable to connect to MySQL!\n\n".
        "%s\n\n".
        "Make sure Phabricator and MySQL are correctly configured.",
        $ex->getMessage());

      $this->newIssue('mysql.connect')
        ->setName(pht('Can Not Connect to MySQL'))
        ->setMessage($message)
        ->setIsFatal(true)
        ->addPhabricatorConfig('mysql.host')
        ->addPhabricatorConfig('mysql.user')
        ->addPhabricatorConfig('mysql.pass');
      return;
    }

    $engines = queryfx_all($conn_raw, 'SHOW ENGINES');
    $engines = ipull($engines, 'Support', 'Engine');

    $innodb = idx($engines, 'InnoDB');
    if ($innodb != 'YES' && $innodb != 'DEFAULT') {
      $message = pht(
        "The 'InnoDB' engine is not available in MySQL. Enable InnoDB in ".
        "your MySQL configuration.".
        "\n\n".
        "(If you aleady created tables, MySQL incorrectly used some other ".
        "engine to create them. You need to convert them or drop and ".
        "reinitialize them.)");

      $this->newIssue('mysql.innodb')
        ->setName(pht('MySQL InnoDB Engine Not Available'))
        ->setMessage($message)
        ->setIsFatal(true);
      return;
    }

    $namespace = PhabricatorEnv::getEnvConfig('storage.default-namespace');

    $databases = queryfx_all($conn_raw, 'SHOW DATABASES');
    $databases = ipull($databases, 'Database', 'Database');

    if (empty($databases[$namespace.'_meta_data'])) {
      $message = pht(
        'Run the storage upgrade script to setup Phabricator\'s database '.
        'schema.');

      $this->newIssue('storage.upgrade')
        ->setName(pht('Setup MySQL Schema'))
        ->setMessage($message)
        ->setIsFatal(true)
        ->addCommand(hsprintf('<tt>phabricator/ $</tt> ./bin/storage upgrade'));
    }
  }
}
