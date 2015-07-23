<?php

final class PhabricatorDatabaseSetupCheck extends PhabricatorSetupCheck {

  public function getDefaultGroup() {
    return self::GROUP_IMPORTANT;
  }

  public function getExecutionOrder() {
    // This must run after basic PHP checks, but before most other checks.
    return 0.5;
  }

  protected function executeChecks() {
    $conf = PhabricatorEnv::newObjectFromConfig('mysql.configuration-provider');
    $conn_user = $conf->getUser();
    $conn_pass = $conf->getPassword();
    $conn_host = $conf->getHost();
    $conn_port = $conf->getPort();

    ini_set('mysql.connect_timeout', 2);

    $config = array(
      'user'      => $conn_user,
      'pass'      => $conn_pass,
      'host'      => $conn_host,
      'port'      => $conn_port,
      'database'  => null,
    );

    $conn_raw = PhabricatorEnv::newObjectFromConfig(
      'mysql.implementation',
      array($config));

    try {
      queryfx($conn_raw, 'SELECT 1');
    } catch (AphrontConnectionQueryException $ex) {
      $message = pht(
        "Unable to connect to MySQL!\n\n".
        "%s\n\n".
        "Make sure Phabricator and MySQL are correctly configured.",
        $ex->getMessage());

      $this->newIssue('mysql.connect')
        ->setName(pht('Can Not Connect to MySQL'))
        ->setMessage($message)
        ->setIsFatal(true)
        ->addRelatedPhabricatorConfig('mysql.host')
        ->addRelatedPhabricatorConfig('mysql.port')
        ->addRelatedPhabricatorConfig('mysql.user')
        ->addRelatedPhabricatorConfig('mysql.pass');
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
        "Run the storage upgrade script to setup Phabricator's database ".
        "schema.");

      $this->newIssue('storage.upgrade')
        ->setName(pht('Setup MySQL Schema'))
        ->setMessage($message)
        ->setIsFatal(true)
        ->addCommand(hsprintf('<tt>phabricator/ $</tt> ./bin/storage upgrade'));
    } else {

      $config['database'] = $namespace.'_meta_data';
      $conn_meta = PhabricatorEnv::newObjectFromConfig(
        'mysql.implementation',
        array($config));

      $applied = queryfx_all($conn_meta, 'SELECT patch FROM patch_status');
      $applied = ipull($applied, 'patch', 'patch');

      $all = PhabricatorSQLPatchList::buildAllPatches();
      $diff = array_diff_key($all, $applied);

      if ($diff) {
        $this->newIssue('storage.patch')
          ->setName(pht('Upgrade MySQL Schema'))
          ->setMessage(
            pht(
              "Run the storage upgrade script to upgrade Phabricator's ".
              "database schema. Missing patches:<br />%s<br />",
              phutil_implode_html(phutil_tag('br'), array_keys($diff))))
          ->addCommand(
            hsprintf('<tt>phabricator/ $</tt> ./bin/storage upgrade'));
      }
    }


    $host = PhabricatorEnv::getEnvConfig('mysql.host');
    $matches = null;
    if (preg_match('/^([^:]+):(\d+)$/', $host, $matches)) {
      $host = $matches[1];
      $port = $matches[2];

      $this->newIssue('storage.mysql.hostport')
        ->setName(pht('Deprecated mysql.host Format'))
        ->setSummary(
          pht(
            'Move port information from `%s` to `%s` in your config.',
            'mysql.host',
            'mysql.port'))
        ->setMessage(
          pht(
            'Your `%s` configuration contains a port number, but this usage '.
            'is deprecated. Instead, put the port number in `%s`.',
            'mysql.host',
            'mysql.port'))
        ->addPhabricatorConfig('mysql.host')
        ->addPhabricatorConfig('mysql.port')
        ->addCommand(
          hsprintf(
            '<tt>phabricator/ $</tt> ./bin/config set mysql.host %s',
            $host))
        ->addCommand(
          hsprintf(
            '<tt>phabricator/ $</tt> ./bin/config set mysql.port %s',
            $port));
    }

  }
}
