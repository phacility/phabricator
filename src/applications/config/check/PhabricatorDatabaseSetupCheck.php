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

    $refs = PhabricatorDatabaseRef::queryAll();
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

    // NOTE: It's possible that replication is broken but we have not been
    // granted permission to "SHOW SLAVE STATUS" so we can't figure it out.
    // We allow this kind of configuration and survive these checks, trusting
    // that operations knows what they're doing. This issue is shown on the
    // "Database Servers" console.

    switch ($ref->getReplicaStatus()) {
      case PhabricatorDatabaseRef::REPLICATION_MASTER_REPLICA:
        $message = pht(
          'Database host "%s" is configured as a master, but is replicating '.
          'another host. This is dangerous and can mangle or destroy data. '.
          'Only replicas should be replicating. Stop replication on the '.
          'host or reconfigure Phabricator.',
          $ref->getRefKey());

        $this->newIssue('db.master.replicating')
          ->setName(pht('Replicating Master'))
          ->setIsFatal(true)
          ->setMessage($message);

        return true;
      case PhabricatorDatabaseRef::REPLICATION_REPLICA_NONE:
      case PhabricatorDatabaseRef::REPLICATION_NOT_REPLICATING:
        if (!$ref->getIsMaster()) {
          $message = pht(
            'Database replica "%s" is listed as a replica, but is not '.
            'currently replicating. You are vulnerable to data loss if '.
            'the master fails.',
            $ref->getRefKey());

          // This isn't a fatal because it can normally only put data at risk,
          // not actually do anything destructive or unrecoverable.

          $this->newIssue('db.replica.not-replicating')
            ->setName(pht('Nonreplicating Replica'))
            ->setMessage($message);
        }
        break;
    }

    // If we have more than one master, we require that the cluster database
    // configuration written to each database node is exactly the same as the
    // one we are running with.
    $masters = PhabricatorDatabaseRef::getAllMasterDatabaseRefs();
    if (count($masters) > 1) {
      $state_actual = queryfx_one(
        $conn_meta,
        'SELECT stateValue FROM %T WHERE stateKey = %s',
        PhabricatorStorageManagementAPI::TABLE_HOSTSTATE,
        'cluster.databases');
      if ($state_actual) {
        $state_actual = $state_actual['stateValue'];
      }

      $state_expect = $ref->getPartitionStateForCommit();

      if ($state_expect !== $state_actual) {
        $message = pht(
          'Database host "%s" has a configured cluster state which disagrees '.
          'with the state on this host ("%s"). Run `bin/storage partition` '.
          'to commit local state to the cluster. This host may have started '.
          'with an out-of-date configuration.',
          $ref->getRefKey(),
          php_uname('n'));

        $this->newIssue('db.state.desync')
          ->setName(pht('Cluster Configuration Out of Sync'))
          ->setMessage($message)
          ->setIsFatal(true);
        return true;
      }
    }
  }

}
