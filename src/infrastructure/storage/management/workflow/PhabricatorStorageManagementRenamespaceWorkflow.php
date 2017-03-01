<?php

final class PhabricatorStorageManagementRenamespaceWorkflow
  extends PhabricatorStorageManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('renamespace')
      ->setExamples(
        '**renamespace** [__options__] '.
        '--input __dump.sql__ --from __old__ --to __new__ > __out.sql__')
      ->setSynopsis(pht('Change the database namespace of a .sql dump file.'))
      ->setArguments(
        array(
          array(
            'name' => 'input',
            'param' => 'file',
            'help' => pht('SQL dumpfile to process.'),
          ),
          array(
            'name' => 'live',
            'help' => pht(
              'Generate a live dump instead of processing a file on disk.'),
          ),
          array(
            'name' => 'from',
            'param' => 'namespace',
            'help' => pht('Current database namespace used by dumpfile.'),
          ),
          array(
            'name' => 'to',
            'param' => 'namespace',
            'help' => pht('Desired database namespace for output.'),
          ),
          array(
            'name' => 'output',
            'param' => 'file',
            'help' => pht('Write output directly to a file on disk.'),
          ),
          array(
            'name' => 'compress',
            'help' => pht('Emit gzipped output instead of plain text.'),
          ),
          array(
            'name' => 'overwrite',
            'help' => pht(
              'With __--output__, write to disk even if the file already '.
              'exists.'),
          ),
        ));
  }

  protected function isReadOnlyWorkflow() {
    return true;
  }

  public function didExecute(PhutilArgumentParser $args) {
    $console = PhutilConsole::getConsole();

    $input = $args->getArg('input');
    $is_live = $args->getArg('live');
    if (!strlen($input) && !$is_live) {
      throw new PhutilArgumentUsageException(
        pht(
          'Specify the dumpfile to read with "--input", or use "--live" to '.
          'generate one automatically.'));
    }

    $from = $args->getArg('from');
    if (!strlen($from)) {
      throw new PhutilArgumentUsageException(
        pht(
          'Specify namespace to rename from with %s.',
          '--from'));
    }

    $to = $args->getArg('to');
    if (!strlen($to)) {
      throw new PhutilArgumentUsageException(
        pht(
          'Specify namespace to rename to with %s.',
          '--to'));
    }


    $output_file = $args->getArg('output');
    $is_overwrite = $args->getArg('overwrite');
    $is_compress = $args->getArg('compress');

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

    if ($is_live) {
      $api = $this->getSingleAPI();
      $ref_key = $api->getRef()->getRefKey();

      $root = dirname(phutil_get_library_root('phabricator'));

      $future = new ExecFuture(
        '%R dump --ref %s',
        $root.'/bin/storage',
        $ref_key);

      $lines = new LinesOfALargeExecFuture($future);
    } else {
      $lines = new LinesOfALargeFile($input);
    }

    if ($output_file === null) {
      $file = fopen('php://stdout', 'wb');
      $output_name = pht('stdout');
    } else {
      if ($is_compress) {
        $file = gzopen($output_file, 'wb');
      } else {
        $file = fopen($output_file, 'wb');
      }
      $output_name = $output_file;
    }

    if (!$file) {
      throw new Exception(
        pht(
          'Failed to open output file "%s" for writing.',
          $output_name));
    }

    $name_pattern = preg_quote($from, '@');

    $patterns = array(
      'use' => '@^(USE `)('.$name_pattern.')(_.*)$@',
      'create' => '@^(CREATE DATABASE /\*.*?\*/ `)('.$name_pattern.')(_.*)$@',
    );

    $found = array_fill_keys(array_keys($patterns), 0);

    try {
      $matches = null;
      foreach ($lines as $line) {

        foreach ($patterns as $key => $pattern) {
          if (preg_match($pattern, $line, $matches)) {
            $line = $matches[1].$to.$matches[3];
            $found[$key]++;
          }
        }

        $data = $line."\n";

        if ($is_compress) {
          $bytes = gzwrite($file, $data);
        } else {
          $bytes = fwrite($file, $data);
        }

        if ($bytes !== strlen($data)) {
          throw new Exception(
            pht(
              'Failed to write %d byte(s) to "%s".',
              new PhutilNumber(strlen($data)),
              $output_name));
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
      try {
        if ($output_file !== null) {
          Filesystem::remove($output_file);
        }
      } catch (Exception $ex) {
        // Ignore any exception.
      }

      throw $ex;
    }

    // Give the user a chance to catch things if the results are crazy.
    $console->writeErr(
      pht(
        'Adjusted **%s** create statements and **%s** use statements.',
        new PhutilNumber($found['create']),
        new PhutilNumber($found['use']))."\n");

    return 0;
  }

}
