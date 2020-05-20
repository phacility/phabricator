<?php

final class PhabricatorConfigManagementSetWorkflow
  extends PhabricatorConfigManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('set')
      ->setExamples(
        "**set** __key__ __value__\n".
        "**set** __key__ --stdin < value.json")
      ->setSynopsis(pht('Set a local configuration value.'))
      ->setArguments(
        array(
          array(
            'name'  => 'database',
            'help'  => pht(
              'Update configuration in the database instead of '.
              'in local configuration.'),
          ),
          array(
            'name' => 'stdin',
            'help' => pht('Read option value from stdin.'),
          ),
          array(
            'name'      => 'args',
            'wildcard'  => true,
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $argv = $args->getArg('args');
    if (!$argv) {
      throw new PhutilArgumentUsageException(
        pht('Specify the configuration key you want to set.'));
    }

    $is_stdin = $args->getArg('stdin');

    $key = $argv[0];

    if ($is_stdin) {
      if (count($argv) > 1) {
        throw new PhutilArgumentUsageException(
          pht(
            'Too many arguments: expected only a configuration key when '.
            'using "--stdin".'));
      }

      fprintf(STDERR, tsprintf("%s\n", pht('Reading value from stdin...')));
      $value = file_get_contents('php://stdin');
    } else {
      if (count($argv) == 1) {
        throw new PhutilArgumentUsageException(
          pht(
            'Specify a value to set the configuration key "%s" to, or '.
            'use "--stdin" to read a value from stdin.',
            $key));
      }

      if (count($argv) > 2) {
        throw new PhutilArgumentUsageException(
          pht(
            'Too many arguments: expected one key and one value.'));
      }

      $value = $argv[1];
    }

    $options = PhabricatorApplicationConfigOptions::loadAllOptions();
    if (empty($options[$key])) {
      throw new PhutilArgumentUsageException(
        pht(
          'Configuration key "%s" is unknown. Use "bin/config list" to list '.
          'all known keys.',
          $key));
    }

    $option = $options[$key];

    $type = $option->newOptionType();
    if ($type) {
      try {
        $value = $type->newValueFromCommandLineValue(
          $option,
          $value);
        $type->validateStoredValue($option, $value);
      } catch (PhabricatorConfigValidationException $ex) {
        throw new PhutilArgumentUsageException($ex->getMessage());
      }
    } else {
      // NOTE: For now, this handles both "wild" values and custom types.
      $type = $option->getType();
      switch ($type) {
        default:
          $value = json_decode($value, true);
          if (!is_array($value)) {
            switch ($type) {
              default:
                $message = pht(
                  'Configuration key "%s" is of type "%s". Specify it in JSON.',
                  $key,
                  $type);
                break;
            }
            throw new PhutilArgumentUsageException($message);
          }
          break;
      }
    }

    $use_database = $args->getArg('database');
    if ($option->getLocked() && $use_database) {
      throw new PhutilArgumentUsageException(
        pht(
          'Config key "%s" is locked and can only be set in local '.
          'configuration. To learn more, see "%s" in the documentation.',
          $key,
          pht('Configuration Guide: Locked and Hidden Configuration')));
    }

    try {
      $option->getGroup()->validateOption($option, $value);
    } catch (PhabricatorConfigValidationException $validation) {
      // Convert this into a usage exception so we don't dump a stack trace.
      throw new PhutilArgumentUsageException($validation->getMessage());
    }

    if ($use_database) {
      $config_entry = PhabricatorConfigEntry::loadConfigEntry($key);
      $config_entry->setValue($value);

      // If the entry has been deleted, resurrect it.
      $config_entry->setIsDeleted(0);

      $config_entry->save();

      $write_message = pht(
        'Wrote configuration key "%s" to database storage.',
        $key);
    } else {
      $config_source = new PhabricatorConfigLocalSource();

      $local_path = $config_source->getReadablePath();

      try {
        $config_source->setKeys(array($key => $value));
      } catch (FilesystemException $ex) {
        throw new PhutilArgumentUsageException(
          pht(
            'Local path "%s" is not writable. This file must be writable '.
            'so that "bin/config" can store configuration.',
            Filesystem::readablePath($local_path)));
      }

      $write_message = pht(
        'Wrote configuration key "%s" to local storage (in file "%s").',
        $key,
        $local_path);
    }

    echo tsprintf(
      "<bg:green>** %s **</bg> %s\n",
      pht('DONE'),
      $write_message);

    return 0;
  }

}
