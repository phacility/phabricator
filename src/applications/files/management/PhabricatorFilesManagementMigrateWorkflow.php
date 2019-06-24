<?php

final class PhabricatorFilesManagementMigrateWorkflow
  extends PhabricatorFilesManagementWorkflow {

  protected function didConstruct() {
    $arguments = $this->newIteratorArguments();

    $arguments[] = array(
      'name' => 'engine',
      'param' => 'storage-engine',
      'help' => pht('Migrate to the named storage engine.'),
    );

    $arguments[] = array(
      'name' => 'dry-run',
      'help' => pht('Show what would be migrated.'),
    );

    $arguments[] = array(
      'name' => 'min-size',
      'param' => 'bytes',
      'help' => pht(
        'Do not migrate data for files which are smaller than a given '.
        'filesize.'),
    );

    $arguments[] = array(
      'name' => 'max-size',
      'param' => 'bytes',
      'help' => pht(
        'Do not migrate data for files which are larger than a given '.
        'filesize.'),
    );

    $arguments[] = array(
      'name' => 'copy',
      'help' => pht(
        'Copy file data instead of moving it: after migrating, do not '.
        'remove the old data even if it is no longer referenced.'),
    );

    $arguments[] = array(
      'name' => 'local-disk-source',
      'param' => 'path',
      'help' => pht(
        'When migrating from a local disk source, use the specified '.
        'path as the root directory.'),
    );

    $this
      ->setName('migrate')
      ->setSynopsis(pht('Migrate files between storage engines.'))
      ->setArguments($arguments);
  }

  public function execute(PhutilArgumentParser $args) {

    // See T13306. This flag allows you to import files from a backup of
    // local disk storage into some other engine. When the caller provides
    // the flag, we override the local disk engine configuration and treat
    // it as though it is configured to use the specified location.

    $local_disk_source = $args->getArg('local-disk-source');
    if (strlen($local_disk_source)) {
      $path = Filesystem::resolvePath($local_disk_source);
      try {
        Filesystem::assertIsDirectory($path);
      } catch (FilesystemException $ex) {
        throw new PhutilArgumentUsageException(
          pht(
            'The "--local-disk-source" argument must point to a valid, '.
            'readable directory on local disk.'));
      }

      $env = PhabricatorEnv::beginScopedEnv();
      $env->overrideEnvConfig('storage.local-disk.path', $path);
    }

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
    $is_dry_run = $args->getArg('dry-run');

    $min_size = (int)$args->getArg('min-size');
    $max_size = (int)$args->getArg('max-size');

    $is_copy = $args->getArg('copy');

    $failed = array();
    $engines = PhabricatorFileStorageEngine::loadAllEngines();
    $total_bytes = 0;
    $total_files = 0;
    foreach ($iterator as $file) {
      $monogram = $file->getMonogram();

      // See T7148. When we export data for an instance, we copy all the data
      // for Files from S3 into the database dump so that the database dump is
      // a complete, standalone archive of all the data. In the general case,
      // installs may have a similar process using "--copy" to create a more
      // complete backup.

      // When doing this, we may run into temporary files which have been
      // deleted between the time we took the original dump and the current
      // timestamp. These files can't be copied since the data no longer
      // exists: the daemons on the live install already deleted it.

      // Simply avoid this whole mess by declining to migrate expired temporary
      // files. They're as good as dead anyway.

      $ttl = $file->getTTL();
      if ($ttl) {
        if ($ttl < PhabricatorTime::getNow()) {
          echo tsprintf(
            "%s\n",
            pht(
              '%s: Skipping expired temporary file.',
              $monogram));
          continue;
        }
      }

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
          $file->migrateToEngine($target_engine, $is_copy);
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
