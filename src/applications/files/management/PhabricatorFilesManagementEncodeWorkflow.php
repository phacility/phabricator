<?php

final class PhabricatorFilesManagementEncodeWorkflow
  extends PhabricatorFilesManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('encode')
      ->setSynopsis(
        pht('Change the storage encoding of files.'))
      ->setArguments(
        array(
          array(
            'name' => 'as',
            'param' => 'format',
            'help' => pht('Select the storage format to use.'),
          ),
          array(
            'name' => 'key',
            'param' => 'keyname',
            'help' => pht('Select a specific storage key.'),
          ),
          array(
            'name' => 'all',
            'help' => pht('Change encoding for all files.'),
          ),
          array(
            'name' => 'force',
            'help' => pht(
              'Re-encode files which are already stored in the target '.
              'encoding.'),
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
          'Either specify a list of files to encode, or use --all to '.
          'encode all files.'));
    }

    $force = (bool)$args->getArg('force');

    $format_list = PhabricatorFileStorageFormat::getAllFormats();
    $format_list = array_keys($format_list);
    $format_list = implode(', ', $format_list);

    $format_key = $args->getArg('as');
    if (!strlen($format_key)) {
      throw new PhutilArgumentUsageException(
        pht(
          'Use --as <format> to select a target encoding format. Available '.
          'formats are: %s.',
          $format_list));
    }

    $format = PhabricatorFileStorageFormat::getFormat($format_key);
    if (!$format) {
      throw new PhutilArgumentUsageException(
        pht(
          'Storage format "%s" is not valid. Available formats are: %s.',
          $format_key,
          $format_list));
    }

    $key_name = $args->getArg('key');
    if (strlen($key_name)) {
      $format->selectMasterKey($key_name);
    }

    $engines = PhabricatorFileStorageEngine::loadAllEngines();

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
            '%s: Stored as chunks, no data to encode directly.',
            $monogram));
        continue;
      }

      if (($file->getStorageFormat() == $format_key) && !$force) {
        echo tsprintf(
          "%s\n",
          pht(
            '%s: Already encoded in target format.',
            $monogram));
        continue;
      }

      echo tsprintf(
        "%s\n",
        pht(
          '%s: Changing encoding from "%s" to "%s".',
          $monogram,
          $file->getStorageFormat(),
          $format_key));

      try {
        $file->migrateToStorageFormat($format);

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
