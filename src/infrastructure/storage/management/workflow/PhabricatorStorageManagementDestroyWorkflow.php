<?php

final class PhabricatorStorageManagementDestroyWorkflow
  extends PhabricatorStorageManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('destroy')
      ->setExamples('**destroy** [__options__]')
      ->setSynopsis(pht('Permanently destroy all storage and data.'))
      ->setArguments(
        array(
          array(
            'name'  => 'unittest-fixtures',
            'help'  => pht(
              'Restrict **destroy** operations to databases created '.
              'by %s test fixtures.',
              'PhabricatorTestCase'),
          ),
        ));
  }

  public function didExecute(PhutilArgumentParser $args) {
    $console = PhutilConsole::getConsole();

    if (!$this->isDryRun() && !$this->isForce()) {
      $console->writeOut(
        phutil_console_wrap(
          pht(
            'Are you completely sure you really want to permanently destroy '.
            'all storage for Phabricator data? This operation can not be '.
            'undone and your data will not be recoverable if you proceed.')));

      if (!phutil_console_confirm(pht('Permanently destroy all data?'))) {
        $console->writeOut("%s\n", pht('Cancelled.'));
        exit(1);
      }

      if (!phutil_console_confirm(pht('Really destroy all data forever?'))) {
        $console->writeOut("%s\n", pht('Cancelled.'));
        exit(1);
      }
    }

    $api     = $this->getAPI();
    $patches = $this->getPatches();

    if ($args->getArg('unittest-fixtures')) {
      $conn = $api->getConn(null);
      $databases = queryfx_all(
        $conn,
        'SELECT DISTINCT(TABLE_SCHEMA) AS db '.
        'FROM INFORMATION_SCHEMA.TABLES '.
        'WHERE TABLE_SCHEMA LIKE %>',
        PhabricatorTestCase::NAMESPACE_PREFIX);
      $databases = ipull($databases, 'db');
    } else {
      $databases   = $api->getDatabaseList($patches);
      $databases[] = $api->getDatabaseName('meta_data');

      // These are legacy databases that were dropped long ago. See T2237.
      $databases[] = $api->getDatabaseName('phid');
      $databases[] = $api->getDatabaseName('directory');
    }

    foreach ($databases as $database) {
      if ($this->isDryRun()) {
        $console->writeOut(
          "%s\n",
          pht("DRYRUN: Would drop database '%s'.", $database));
      } else {
        $console->writeOut(
          "%s\n",
          pht("Dropping database '%s'...", $database));
        queryfx(
          $api->getConn(null),
          'DROP DATABASE IF EXISTS %T',
          $database);
      }
    }

    if (!$this->isDryRun()) {
      $console->writeOut("%s\n", pht('Storage was destroyed.'));
    }

    return 0;
  }

}
