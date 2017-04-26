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
              'generating a CHANGE MASTER statement in the output.'),
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
            'name' => 'overwrite',
            'help' => pht(
              'With __--output__, overwrite the output file if it already '.
              'exists.'),
          ),
        ));
  }

  protected function isReadOnlyWorkflow() {
    return true;
  }

  public function didExecute(PhutilArgumentParser $args) {
    $api = $this->getSingleAPI();
    $patches = $this->getPatches();

    $console = PhutilConsole::getConsole();

    $applied = $api->getAppliedPatches();
    if ($applied === null) {
      $namespace = $api->getNamespace();
      $console->writeErr(
        pht(
          '**Storage Not Initialized**: There is no database storage '.
          'initialized in this storage namespace ("%s"). Use '.
          '**%s** to initialize storage.',
          $namespace,
          './bin/storage upgrade'));
      return 1;
    }

    $databases = $api->getDatabaseList($patches, true);

    list($host, $port) = $this->getBareHostAndPort($api->getHost());

    $has_password = false;

    $password = $api->getPassword();
    if ($password) {
      if (strlen($password->openEnvelope())) {
        $has_password = true;
      }
    }

    $output_file = $args->getArg('output');
    $is_compress = $args->getArg('compress');
    $is_overwrite = $args->getArg('overwrite');

    if ($is_compress) {
      if ($output_file === null) {
        throw new PhutilArgumentUsageException(
          pht(
            'The "--compress" flag can only be used alongside "--output".'));
      }
    }

    if ($is_overwrite) {
      if ($output_file === null) {
        throw new PhutilArgumentUsageException(
          pht(
            'The "--overwrite" flag can only be used alongside "--output".'));
      }
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

    $argv = array();
    $argv[] = '--hex-blob';
    $argv[] = '--single-transaction';
    $argv[] = '--default-character-set=utf8';

    if ($args->getArg('for-replica')) {
      $argv[] = '--master-data';
    }

    $argv[] = '-u';
    $argv[] = $api->getUser();
    $argv[] = '-h';
    $argv[] = $host;

    if ($port) {
      $argv[] = '--port';
      $argv[] = $port;
    }

    $argv[] = '--databases';
    foreach ($databases as $database) {
      $argv[] = $database;
    }


    if ($has_password) {
      $command = csprintf('mysqldump -p%P %Ls', $password, $argv);
    } else {
      $command = csprintf('mysqldump %Ls', $argv);
    }

    // Decrease the CPU priority of this process so it doesn't contend with
    // other more important things.
    if (function_exists('proc_nice')) {
      proc_nice(19);
    }


    // If we aren't writing to a file, just passthru the command.
    if ($output_file === null) {
      return phutil_passthru('%C', $command);
    }

    // If we are writing to a file, stream the command output to disk. This
    // mode makes sure the whole command fails if there's an error (commonly,
    // a full disk). See T6996 for discussion.

    if ($is_compress) {
      $file = gzopen($output_file, 'wb1');
    } else {
      $file = fopen($output_file, 'wb');
    }

    if (!$file) {
      throw new Exception(
        pht(
          'Failed to open file "%s" for writing.',
          $file));
    }

    $future = new ExecFuture('%C', $command);

    try {
      $iterator = id(new FutureIterator(array($future)))
        ->setUpdateInterval(0.100);
      foreach ($iterator as $ready) {
        list($stdout, $stderr) = $future->read();
        $future->discardBuffers();

        if (strlen($stderr)) {
          fwrite(STDERR, $stderr);
        }

        if (strlen($stdout)) {
          if ($is_compress) {
            $ok = gzwrite($file, $stdout);
          } else {
            $ok = fwrite($file, $stdout);
          }

          if ($ok !== strlen($stdout)) {
            throw new Exception(
              pht(
                'Failed to write %d byte(s) to file "%s".',
                new PhutilNumber(strlen($stdout)),
                $output_file));
          }
        }

        if ($ready !== null) {
          $ready->resolvex();
        }
      }

      if ($is_compress) {
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
        Filesystem::remove($output_file);
      } catch (Exception $ex) {
        // Ignore any errors we hit.
      }

      throw $ex;
    }

    return 0;
  }

}
