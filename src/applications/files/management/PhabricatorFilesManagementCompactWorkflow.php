<?php

final class PhabricatorFilesManagementCompactWorkflow
  extends PhabricatorFilesManagementWorkflow {

  protected function didConstruct() {
    $arguments = $this->newIteratorArguments();
    $arguments[] = array(
      'name' => 'dry-run',
      'help' => pht('Show what would be compacted.'),
    );

    $this
      ->setName('compact')
      ->setSynopsis(
        pht(
          'Merge identical files to share the same storage. In some cases, '.
          'this can repair files with missing data.'))
      ->setArguments($arguments);
  }

  public function execute(PhutilArgumentParser $args) {
    $console = PhutilConsole::getConsole();

    $iterator = $this->buildIterator($args);
    $is_dry_run = $args->getArg('dry-run');

    foreach ($iterator as $file) {
      $monogram = $file->getMonogram();

      $hash = $file->getContentHash();
      if (!$hash) {
        $console->writeOut(
          "%s\n",
          pht('%s: No content hash.', $monogram));
        continue;
      }

      // Find other files with the same content hash. We're going to point
      // them at the data for this file.
      $similar_files = id(new PhabricatorFile())->loadAllWhere(
        'contentHash = %s AND id != %d AND
          (storageEngine != %s OR storageHandle != %s)',
        $hash,
        $file->getID(),
        $file->getStorageEngine(),
        $file->getStorageHandle());
      if (!$similar_files) {
        $console->writeOut(
          "%s\n",
          pht('%s: No other files with the same content hash.', $monogram));
        continue;
      }

      // Only compact files into this one if we can load the data. This
      // prevents us from breaking working files if we're missing some data.
      try {
        $data = $file->loadFileData();
      } catch (Exception $ex) {
        $data = null;
      }

      if ($data === null) {
        $console->writeOut(
          "%s\n",
          pht(
            '%s: Unable to load file data; declining to compact.',
            $monogram));
        continue;
      }

      foreach ($similar_files as $similar_file) {
        if ($is_dry_run) {
          $console->writeOut(
            "%s\n",
            pht(
              '%s: Would compact storage with %s.',
              $monogram,
              $similar_file->getMonogram()));
          continue;
        }

        $console->writeOut(
          "%s\n",
          pht(
            '%s: Compacting storage with %s.',
            $monogram,
            $similar_file->getMonogram()));

        $old_instance = null;
        try {
          $old_instance = $similar_file->instantiateStorageEngine();
          $old_engine = $similar_file->getStorageEngine();
          $old_handle = $similar_file->getStorageHandle();
        } catch (Exception $ex) {
          // If the old stuff is busted, we just won't try to delete the
          // old data.
          phlog($ex);
        }

        $similar_file
          ->setStorageEngine($file->getStorageEngine())
          ->setStorageHandle($file->getStorageHandle())
          ->save();

        if ($old_instance) {
          $similar_file->deleteFileDataIfUnused(
            $old_instance,
            $old_engine,
            $old_handle);
        }
      }
    }

    return 0;
  }

}
