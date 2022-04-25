<?php

final class PhabricatorStorageManagementDumpWorkflow
  extends PhabricatorStorageManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('dump')
      ->setExamples('**dump** [__options__]')
      ->setSynopsis(pht('Dump all data in storage to stdout.'))
      ->setArguments(
        array(
          array(
            'name' => 'for-replica',
            'help' => pht(
              'Add __--master-data__ to the __mysqldump__ command, '.
              'generating a CHANGE MASTER statement in the output. This '.
              'option also dumps all data, including caches.'),
          ),
          array(
            'name' => 'output',
            'param' => 'file',
            'help' => pht(
              'Write output directly to disk. This handles errors better '.
              'than using pipes. Use with __--compress__ to gzip the '.
              'output.'),
          ),
          array(
            'name' => 'compress',
            'help' => pht(
              'With __--output__, write a compressed file to disk instead '.
              'of a plaintext file.'),
          ),
          array(
            'name' => 'no-indexes',
            'help' => pht(
              'Do not dump data in rebuildable index tables. This means '.
              'backups are smaller and faster, but you will need to manually '.
              'rebuild indexes after performing a restore.'),
          ),
          array(
            'name' => 'overwrite',
            'help' => pht(
              'With __--output__, overwrite the output file if it already '.
              'exists.'),
          ),
          array(
            'name' => 'database',
            'param' => 'database-name',
            'help' => pht(
              'Dump only tables in the named database (or databases, if '.
              'the flag is repeated). Specify database names without the '.
              'namespace prefix (that is: use "differential", not '.
              '"phabricator_differential").'),
            'repeat' => true,
          ),
        ));
  }

  protected function isReadOnlyWorkflow() {
    return true;
  }

  public function didExecute(PhutilArgumentParser $args) {
    $output_file = $args->getArg('output');
    $is_compress = $args->getArg('compress');
    $is_overwrite = $args->getArg('overwrite');
    $is_noindex = $args->getArg('no-indexes');
    $is_replica = $args->getArg('for-replica');

    $database_filter = $args->getArg('database');

    if ($is_compress) {
      if ($output_file === null) {
        throw new PhutilArgumentUsageException(
          pht(
            'The "--compress" flag can only be used alongside "--output".'));
      }

      if (!function_exists('gzopen')) {
        throw new PhutilArgumentUsageException(
          pht(
            'The "--compress" flag requires the PHP "zlib" extension, but '.
            'that extension is not available. Install the extension or '.
            'omit the "--compress" option.'));
      }
    }

    if ($is_overwrite) {
      if ($output_file === null) {
        throw new PhutilArgumentUsageException(
          pht(
            'The "--overwrite" flag can only be used alongside "--output".'));
      }
    }

    if ($is_replica && $is_noindex) {
      throw new PhutilArgumentUsageException(
        pht(
          'The "--for-replica" flag can not be used with the '.
          '"--no-indexes" flag. Replication dumps must contain a complete '.
          'representation of database state.'));
    }

    if ($output_file !== null) {
      if (Filesystem::pathExists($output_file)) {
        if (!$is_overwrite) {
          throw new PhutilArgumentUsageException(
            pht(
              'Output file "%s" already exists. Use "--overwrite" '.
              'to overwrite.',
              $output_file));
        }
      }
    }

    $api = $this->getSingleAPI();
    $patches = $this->getPatches();

    $applied = $api->getAppliedPatches();
    if ($applied === null) {
      throw new PhutilArgumentUsageException(
        pht(
          'There is no database storage initialized in the current storage '.
          'namespace ("%s"). Use "bin/storage upgrade" to initialize '.
          'storage or use "--namespace" to choose a different namespace.',
          $api->getNamespace()));
    }

    $ref = $api->getRef();
    $ref_key = $ref->getRefKey();

    $schemata_query = id(new PhabricatorConfigSchemaQuery())
      ->setAPIs(array($api))
      ->setRefs(array($ref));

    $actual_map = $schemata_query->loadActualSchemata();
    $expect_map = $schemata_query->loadExpectedSchemata();

    $schemata = $actual_map[$ref_key];
    $expect = $expect_map[$ref_key];

    if ($database_filter) {
      $internal_names = array();

      $expect_databases = $expect->getDatabases();
      foreach ($expect_databases as $expect_database) {
        $database_name = $expect_database->getName();

        $internal_name = $api->getInternalDatabaseName($database_name);
        if ($internal_name !== null) {
          $internal_names[$internal_name] = $database_name;
        }
      }

      ksort($internal_names);

      $seen = array();
      foreach ($database_filter as $filter) {
        if (!isset($internal_names[$filter])) {
          throw new PhutilArgumentUsageException(
            pht(
              'Database "%s" is unknown. This script can only dump '.
              'databases known to the current version of this software. '.
              'Valid databases are: %s.',
              $filter,
              implode(', ', array_keys($internal_names))));
        }

        if (isset($seen[$filter])) {
          throw new PhutilArgumentUsageException(
            pht(
              'Database "%s" is specified more than once. Specify each '.
              'database at most once.',
              $filter));
        }

        $seen[$filter] = true;
      }

      $dump_databases = array_select_keys($internal_names, $database_filter);
      $dump_databases = array_fuse($dump_databases);
    } else {
      $dump_databases = array_keys($schemata->getDatabases());
      $dump_databases = array_fuse($dump_databases);
    }

    $with_caches = $is_replica;
    $with_indexes = !$is_noindex;

    $targets = array();
    foreach ($schemata->getDatabases() as $database_name => $database) {
      if (!isset($dump_databases[$database_name])) {
        continue;
      }

      $expect_database = $expect->getDatabase($database_name);
      foreach ($database->getTables() as $table_name => $table) {

        // NOTE: It's possible for us to find tables in these database which
        // we don't expect to be there. For example, an older version of
        // Phabricator may have had a table that was later dropped. We assume
        // these are data tables and always dump them, erring on the side of
        // caution.

        $persistence = PhabricatorConfigTableSchema::PERSISTENCE_DATA;
        if ($expect_database) {
          $expect_table = $expect_database->getTable($table_name);
          if ($expect_table) {
            $persistence = $expect_table->getPersistenceType();
          }
        }

        switch ($persistence) {
          case PhabricatorConfigTableSchema::PERSISTENCE_CACHE:
            // When dumping tables, leave the data in cache tables in the
            // database. This will be automatically rebuild after the data
            // is restored and does not need to be persisted in backups.
            $with_data = $with_caches;
            break;
          case PhabricatorConfigTableSchema::PERSISTENCE_INDEX:
            // When dumping tables, leave index data behind of the caller
            // specified "--no-indexes". These tables can be rebuilt manually
            // from other tables, but do not rebuild automatically.
            $with_data = $with_indexes;
            break;
          case PhabricatorConfigTableSchema::PERSISTENCE_DATA:
          default:
            $with_data = true;
            break;
        }

        $targets[] = array(
          'database' => $database_name,
          'table' => $table_name,
          'data' => $with_data,
        );
      }
    }

    list($host, $port) = $this->getBareHostAndPort($api->getHost());

    $has_password = false;

    $password = $api->getPassword();
    if ($password) {
      if (strlen($password->openEnvelope())) {
        $has_password = true;
      }
    }

    $argv = array();
    $argv[] = '--hex-blob';
    $argv[] = '--single-transaction';

    $argv[] = '--default-character-set';
    $argv[] = $api->getClientCharset();

    if ($is_replica) {
      $argv[] = '--master-data';
    }

    $argv[] = '-u';
    $argv[] = $api->getUser();
    $argv[] = '-h';
    $argv[] = $host;

    // MySQL's default "max_allowed_packet" setting is fairly conservative
    // (16MB). If we try to dump a row which is larger than this limit, the
    // dump will fail.

    // We encourage users to increase this limit during setup, but modifying
    // the "[mysqld]" section of the configuration file (instead of
    // "[mysqldump]" section) won't apply to "mysqldump" and we can not easily
    // detect what the "mysqldump" setting is.

    // Since no user would ever reasonably want a dump to fail because a row
    // was too large, just manually force this setting to the largest supported
    // value.

    $argv[] = '--max-allowed-packet';
    $argv[] = '1G';

    if ($port) {
      $argv[] = '--port';
      $argv[] = $port;
    }

    $commands = array();
    foreach ($targets as $target) {
      $target_argv = $argv;

      if (!$target['data']) {
        $target_argv[] = '--no-data';
      }

      if ($has_password) {
        $command = csprintf(
          'mysqldump -p%P %Ls -- %R %R',
          $password,
          $target_argv,
          $target['database'],
          $target['table']);
      } else {
        $command = csprintf(
          'mysqldump %Ls -- %R %R',
          $target_argv,
          $target['database'],
          $target['table']);
      }

      $commands[] = array(
        'command' => $command,
        'database' => $target['database'],
      );
    }


    // Decrease the CPU priority of this process so it doesn't contend with
    // other more important things.
    if (function_exists('proc_nice')) {
      proc_nice(19);
    }

    // If we are writing to a file, stream the command output to disk. This
    // mode makes sure the whole command fails if there's an error (commonly,
    // a full disk). See T6996 for discussion.

    if ($output_file === null) {
      $file = null;
    } else if ($is_compress) {
      $file = gzopen($output_file, 'wb1');
    } else {
      $file = fopen($output_file, 'wb');
    }

    if (($output_file !== null) && !$file) {
      throw new Exception(
        pht(
          'Failed to open file "%s" for writing.',
          $file));
    }

    $created = array();

    try {
      foreach ($commands as $spec) {
        // Because we're dumping database-by-database, we need to generate our
        // own CREATE DATABASE and USE statements.

        $database = $spec['database'];
        $preamble = array();
        if (!isset($created[$database])) {
          $preamble[] =
            "CREATE DATABASE /*!32312 IF NOT EXISTS*/ `{$database}` ".
            "/*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin */;\n";
          $created[$database] = true;
        }
        $preamble[] = "USE `{$database}`;\n";
        $preamble = implode('', $preamble);
        $this->writeData($preamble, $file, $is_compress, $output_file);

        // See T13328. The "mysql" command may produce output very quickly.
        // Don't buffer more than a fixed amount.
        $future = id(new ExecFuture('%C', $spec['command']))
          ->setReadBufferSize(32 * 1024 * 1024);

        $iterator = id(new FutureIterator(array($future)))
          ->setUpdateInterval(0.010);
        foreach ($iterator as $ready) {
          list($stdout, $stderr) = $future->read();
          $future->discardBuffers();

          if (strlen($stderr)) {
            fwrite(STDERR, $stderr);
          }

          $this->writeData($stdout, $file, $is_compress, $output_file);

          if ($ready !== null) {
            $ready->resolvex();
          }
        }
      }

      if (!$file) {
        $ok = true;
      } else if ($is_compress) {
        $ok = gzclose($file);
      } else {
        $ok = fclose($file);
      }

      if ($ok !== true) {
        throw new Exception(
          pht(
            'Failed to close file "%s".',
            $output_file));
      }
    } catch (Exception $ex) {
      // If we might have written a partial file to disk, try to remove it so
      // we don't leave any confusing artifacts laying around.

      try {
        if ($file !== null) {
          Filesystem::remove($output_file);
        }
      } catch (Exception $ex) {
        // Ignore any errors we hit.
      }

      throw $ex;
    }

    return 0;
  }

  private function writeData($data, $file, $is_compress, $output_file) {
    if (!strlen($data)) {
      return;
    }

    if (!$file) {
      $ok = fwrite(STDOUT, $data);
    } else if ($is_compress) {
      $ok = gzwrite($file, $data);
    } else {
      $ok = fwrite($file, $data);
    }

    if ($ok !== strlen($data)) {
      throw new Exception(
        pht(
          'Failed to write %d byte(s) to file "%s".',
          new PhutilNumber(strlen($data)),
          $output_file));
    }
  }

}
