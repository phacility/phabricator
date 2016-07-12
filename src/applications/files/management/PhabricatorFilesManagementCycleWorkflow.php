<?php

final class PhabricatorFilesManagementCycleWorkflow
  extends PhabricatorFilesManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('cycle')
      ->setSynopsis(
        pht('Cycle master key for encrypted files.'))
      ->setArguments(
        array(
          array(
            'name' => 'key',
            'param' => 'keyname',
            'help' => pht('Select a specific storage key to cycle to.'),
          ),
          array(
            'name' => 'all',
            'help' => pht('Change encoding for all files.'),
          ),
          array(
            'name' => 'names',
            'wildcard' => true,
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $iterator = $this->buildIterator($args);
    if (!$iterator) {
      throw new PhutilArgumentUsageException(
        pht(
          'Either specify a list of files to cycle, or use --all to cycle '.
          'all files.'));
    }

    $format_map = PhabricatorFileStorageFormat::getAllFormats();
    $engines = PhabricatorFileStorageEngine::loadAllEngines();

    $key_name = $args->getArg('key');

    $failed = array();
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
            '%s: Stored as chunks, declining to cycle directly.',
            $monogram));
        continue;
      }

      $format_key = $file->getStorageFormat();
      if (empty($format_map[$format_key])) {
        echo tsprintf(
          "%s\n",
          pht(
            '%s: Uses unknown storage format "%s".',
            $monogram,
            $format_key));
        $failed[] = $file;
        continue;
      }

      $format = clone $format_map[$format_key];
      $format->setFile($file);

      if (!$format->canCycleMasterKey()) {
        echo tsprintf(
          "%s\n",
          pht(
            '%s: Storage format ("%s") does not support key cycling.',
            $monogram,
            $format->getStorageFormatName()));
        continue;
      }

      echo tsprintf(
        "%s\n",
        pht(
          '%s: Cycling master key.',
          $monogram));

      try {
        if ($key_name) {
          $format->selectMasterKey($key_name);
        }

        $file->cycleMasterStorageKey($format);

        echo tsprintf(
          "%s\n",
          pht('Done.'));
      } catch (Exception $ex) {
        echo tsprintf(
          "%B\n",
          pht('Failed! %s', (string)$ex));
        $failed[] = $file;
      }
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
