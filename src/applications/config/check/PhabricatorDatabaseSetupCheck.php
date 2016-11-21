<?php

final class PhabricatorDatabaseSetupCheck extends PhabricatorSetupCheck {

  public function getDefaultGroup() {
    return self::GROUP_IMPORTANT;
  }

  public function getExecutionOrder() {
    // This must run after basic PHP checks, but before most other checks.
    return 500;
  }

  protected function executeChecks() {
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

    $refs = PhabricatorDatabaseRef::getActiveDatabaseRefs();
    $refs = mpull($refs, null, 'getRefKey');

    // Test if we can connect to each database first. If we can not connect
    // to a particular database, we only raise a warning: this allows new web
    // nodes to start during a disaster, when some databases may be correctly
    // configured but not reachable.

    $connect_map = array();
    $any_connection = false;
    foreach ($refs as $ref_key => $ref) {
      $conn_raw = $ref->newManagementConnection();

      try {
        queryfx($conn_raw, 'SELECT 1');
        $database_exception = null;
        $any_connection = true;
      } catch (AphrontInvalidCredentialsQueryException $ex) {
        $database_exception = $ex;
      } catch (AphrontConnectionQueryException $ex) {
        $database_exception = $ex;
      }

      if ($database_exception) {
        $connect_map[$ref_key] = $database_exception;
        unset($refs[$ref_key]);
      }
    }

    if ($connect_map) {
      // This is only a fatal error if we could not connect to anything. If
      // possible, we still want to start if some database hosts can not be
      // reached.
      $is_fatal = !$any_connection;

      foreach ($connect_map as $ref_key => $database_exception) {
        $issue = PhabricatorSetupIssue::newDatabaseConnectionIssue(
          $database_exception,
          $is_fatal);
        $this->addIssue($issue);
      }
    }

    foreach ($refs as $ref_key => $ref) {
      if ($this->executeRefChecks($ref)) {
        return;
      }
    }
  }

  private function executeRefChecks(PhabricatorDatabaseRef $ref) {
    $conn_raw = $ref->newManagementConnection();
    $ref_key = $ref->getRefKey();

    $engines = queryfx_all($conn_raw, 'SHOW ENGINES');
    $engines = ipull($engines, 'Support', 'Engine');

    $innodb = idx($engines, 'InnoDB');
    if ($innodb != 'YES' && $innodb != 'DEFAULT') {
      $message = pht(
        'The "InnoDB" engine is not available in MySQL (on host "%s"). '.
        'Enable InnoDB in your MySQL configuration.'.
        "\n\n".
        '(If you aleady created tables, MySQL incorrectly used some other '.
        'engine to create them. You need to convert them or drop and '.
        'reinitialize them.)',
        $ref_key);

      $this->newIssue('mysql.innodb')
        ->setName(pht('MySQL InnoDB Engine Not Available'))
        ->setMessage($message)
        ->setIsFatal(true);

      return true;
    }

    $namespace = PhabricatorEnv::getEnvConfig('storage.default-namespace');

    $databases = queryfx_all($conn_raw, 'SHOW DATABASES');
    $databases = ipull($databases, 'Database', 'Database');

    if (empty($databases[$namespace.'_meta_data'])) {
      $message = pht(
        'Run the storage upgrade script to setup databases (host "%s" has '.
        'not been initialized).',
        $ref_key);

      $this->newIssue('storage.upgrade')
        ->setName(pht('Setup MySQL Schema'))
        ->setMessage($message)
        ->setIsFatal(true)
        ->addCommand(hsprintf('<tt>phabricator/ $</tt> ./bin/storage upgrade'));

      return true;
    }

    $conn_meta = $ref->newApplicationConnection(
      $namespace.'_meta_data');

    $applied = queryfx_all($conn_meta, 'SELECT patch FROM patch_status');
    $applied = ipull($applied, 'patch', 'patch');

    $all = PhabricatorSQLPatchList::buildAllPatches();
    $diff = array_diff_key($all, $applied);

    if ($diff) {
      $message = pht(
        'Run the storage upgrade script to upgrade databases (host "%s" is '.
        'out of date). Missing patches: %s.',
        $ref_key,
        implode(', ', array_keys($diff)));

      $this->newIssue('storage.patch')
        ->setName(pht('Upgrade MySQL Schema'))
        ->setIsFatal(true)
        ->setMessage($message)
        ->addCommand(
          hsprintf('<tt>phabricator/ $</tt> ./bin/storage upgrade'));

      return true;
    }
  }
}
