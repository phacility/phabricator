<?php

final class PhabricatorConfigManagementDeleteWorkflow
  extends PhabricatorConfigManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('delete')
      ->setExamples('**delete** __key__')
      ->setSynopsis(pht('Delete a local configuration value.'))
      ->setArguments(
        array(
          array(
            'name'  => 'database',
            'help'  => pht(
              'Delete configuration in the database instead of '.
              'in local configuration.'),
          ),
          array(
            'name'      => 'args',
            'wildcard'  => true,
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $console = PhutilConsole::getConsole();

    $argv = $args->getArg('args');
    if (count($argv) == 0) {
      throw new PhutilArgumentUsageException(
        pht('Specify a configuration key to delete.'));
    }

    $key = $argv[0];

    if (count($argv) > 1) {
      throw new PhutilArgumentUsageException(
        pht('Too many arguments: expected one key.'));
    }


    $use_database = $args->getArg('database');
    if ($use_database) {
      $config = new PhabricatorConfigDatabaseSource('default');
      $config_type = 'database';
    } else {
      $config = new PhabricatorConfigLocalSource();
      $config_type = 'local';
    }
    $values = $config->getKeys(array($key));
    if (!$values) {
      throw new PhutilArgumentUsageException(
        pht(
          "Configuration key '%s' is not set in %s configuration!",
          $key,
          $config_type));
    }

    if ($use_database) {
      $config_entry = PhabricatorConfigEntry::loadConfigEntry($key);
      $config_entry->setIsDeleted(1);
      $config_entry->save();
    } else {
      $config->deleteKeys(array($key));
    }

    $console->writeOut(
      "%s\n",
      pht("Deleted '%s' from %s configuration.", $key, $config_type));
  }

}
