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
    $master = PhabricatorDatabaseRef::getMasterDatabaseRef();
    if (!$master) {
      // If we're implicitly in read-only mode during disaster recovery,
      // don't bother with these setup checks.
      return;
    }

    $conn_raw = $master->newManagementConnection();

    try {
      queryfx($conn_raw, 'SELECT 1');
      $database_exception = null;
    } catch (AphrontInvalidCredentialsQueryException $ex) {
      $database_exception = $ex;
    } catch (AphrontConnectionQueryException $ex) {
      $database_exception = $ex;
    }

    if ($database_exception) {
      $issue = PhabricatorSetupIssue::newDatabaseConnectionIssue(
        $database_exception);
      $this->addIssue($issue);
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
      $conn_meta = $master->newApplicationConnection(
        $namespace.'_meta_data');

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
