<?php

final class PhabricatorFilesManagementMigrateWorkflow
  extends PhabricatorFilesManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('migrate')
      ->setSynopsis(pht('Migrate files between storage engines.'))
      ->setArguments(
        array(
          array(
            'name'      => 'engine',
            'param'     => 'storage_engine',
            'help'      => pht('Migrate to the named storage engine.'),
          ),
          array(
            'name'      => 'dry-run',
            'help'      => pht('Show what would be migrated.'),
          ),
          array(
            'name' => 'min-size',
            'param' => 'bytes',
            'help' => pht(
              'Do not migrate data for files which are smaller than a given '.
              'filesize.'),
          ),
          array(
            'name' => 'max-size',
            'param' => 'bytes',
            'help' => pht(
              'Do not migrate data for files which are larger than a given '.
              'filesize.'),
          ),
          array(
            'name'      => 'all',
            'help'      => pht('Migrate all files.'),
          ),
          array(
            'name'      => 'names',
            'wildcard'  => true,
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $target_key = $args->getArg('engine');
    if (!$target_key) {
      throw new PhutilArgumentUsageException(
        pht(
          'Specify an engine to migrate to with `%s`. '.
          'Use `%s` to get a list of engines.',
          '--engine',
          'files engines'));
    }

    $target_engine = PhabricatorFile::buildEngine($target_key);

    $iterator = $this->buildIterator($args);
    if (!$iterator) {
      throw new PhutilArgumentUsageException(
        pht(
          'Either specify a list of files to migrate, or use `%s` '.
          'to migrate all files.',
          '--all'));
    }

    $is_dry_run = $args->getArg('dry-run');

    $min_size = (int)$args->getArg('min-size');
    $max_size = (int)$args->getArg('max-size');

    $failed = array();
    $engines = PhabricatorFileStorageEngine::loadAllEngines();
    $total_bytes = 0;
    $total_files = 0;
    foreach ($iterator as $file) {
      $monogram = $file->getMonogram();

      $engine_key = $file->getStorageEngine();
      $engine = idx($engines, $engine_key);

      if (!$engine) {
        echo tsprintf(
          "%s\n",
          pht(
            '%s: Uses unknown storage engine "%s".',
            $monogram,
            $engine_key));
        $failed[] = $file;
        continue;
      }

      if ($engine->isChunkEngine()) {
        echo tsprintf(
          "%s\n",
          pht(
            '%s: Stored as chunks, no data to migrate directly.',
            $monogram));
        continue;
      }

      if ($engine_key === $target_key) {
        echo tsprintf(
          "%s\n",
          pht(
            '%s: Already stored in engine "%s".',
            $monogram,
            $target_key));
        continue;
      }

      $byte_size = $file->getByteSize();

      if ($min_size && ($byte_size < $min_size)) {
        echo tsprintf(
          "%s\n",
          pht(
            '%s: File size (%s) is smaller than minimum size (%s).',
            $monogram,
            phutil_format_bytes($byte_size),
            phutil_format_bytes($min_size)));
        continue;
      }

      if ($max_size && ($byte_size > $max_size)) {
        echo tsprintf(
          "%s\n",
          pht(
            '%s: File size (%s) is larger than maximum size (%s).',
            $monogram,
            phutil_format_bytes($byte_size),
            phutil_format_bytes($max_size)));
        continue;
      }

      if ($is_dry_run) {
        echo tsprintf(
          "%s\n",
          pht(
            '%s: (%s) Would migrate from "%s" to "%s" (dry run)...',
            $monogram,
            phutil_format_bytes($byte_size),
            $engine_key,
            $target_key));
      } else {
        echo tsprintf(
          "%s\n",
          pht(
            '%s: (%s) Migrating from "%s" to "%s"...',
            $monogram,
            phutil_format_bytes($byte_size),
            $engine_key,
            $target_key));
      }

      try {
        if ($is_dry_run) {
          // Do nothing, this is a dry run.
        } else {
          $file->migrateToEngine($target_engine);
        }

        $total_files += 1;
        $total_bytes += $byte_size;

        echo tsprintf(
          "%s\n",
          pht('Done.'));

      } catch (Exception $ex) {
        echo tsprintf(
          "%s\n",
          pht('Failed! %s', (string)$ex));
        $failed[] = $file;

        throw $ex;
      }
    }

    echo tsprintf(
      "%s\n",
      pht(
        'Total Migrated Files: %s',
        new PhutilNumber($total_files)));

    echo tsprintf(
      "%s\n",
      pht(
        'Total Migrated Bytes: %s',
        phutil_format_bytes($total_bytes)));

    if ($is_dry_run) {
      echo tsprintf(
        "%s\n",
        pht(
          'This was a dry run, so no real migrations were performed.'));
    }

    if ($failed) {
      $monograms = mpull($failed, 'getMonogram');

      echo tsprintf(
        "%s\n",
        pht('Failures: %s.', implode(', ', $monograms)));

      return 1;
    }

    return 0;
  }

}
