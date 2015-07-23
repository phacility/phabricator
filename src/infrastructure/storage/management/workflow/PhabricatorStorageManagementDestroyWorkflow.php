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

  public function execute(PhutilArgumentParser $args) {
    $is_dry = $args->getArg('dryrun');
    $is_force = $args->getArg('force');

    if (!$is_dry && !$is_force) {
      echo phutil_console_wrap(
        pht(
          'Are you completely sure you really want to permanently destroy all '.
          'storage for Phabricator data? This operation can not be undone and '.
          'your data will not be recoverable if you proceed.'));

      if (!phutil_console_confirm(pht('Permanently destroy all data?'))) {
        echo pht('Cancelled.')."\n";
        exit(1);
      }

      if (!phutil_console_confirm(pht('Really destroy all data forever?'))) {
        echo pht('Cancelled.')."\n";
        exit(1);
      }
    }

    $api = $this->getAPI();
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
      $databases = $api->getDatabaseList($patches);
      $databases[] = $api->getDatabaseName('meta_data');
      // These are legacy databases that were dropped long ago. See T2237.
      $databases[] = $api->getDatabaseName('phid');
      $databases[] = $api->getDatabaseName('directory');
    }

    foreach ($databases as $database) {
      if ($is_dry) {
        echo pht("DRYRUN: Would drop database '%s'.", $database)."\n";
      } else {
        echo pht("Dropping database '%s'...", $database)."\n";
        queryfx(
          $api->getConn(null),
          'DROP DATABASE IF EXISTS %T',
          $database);
      }
    }

    if (!$is_dry) {
      echo pht('Storage was destroyed.')."\n";
    }

    return 0;
  }

}
