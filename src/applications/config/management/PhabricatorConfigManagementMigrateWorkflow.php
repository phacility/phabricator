<?php

final class PhabricatorConfigManagementMigrateWorkflow
  extends PhabricatorConfigManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('migrate')
      ->setExamples('**migrate**')
      ->setSynopsis(pht(
        'Migrate file-based configuration to more modern storage.'));
  }

  public function execute(PhutilArgumentParser $args) {
    $console = PhutilConsole::getConsole();
    $key_count = 0;

    $options = PhabricatorApplicationConfigOptions::loadAllOptions();
    $local_config = new PhabricatorConfigLocalSource();
    $database_config = new PhabricatorConfigDatabaseSource('default');
    $config_sources = PhabricatorEnv::getConfigSourceStack()->getStack();
    $console->writeOut(
      pht('Migrating file-based config to more modern config...')."\n");
    foreach ($config_sources as $config_source) {
      if (!($config_source instanceof PhabricatorConfigFileSource)) {
        $console->writeOut(
          pht('Skipping config of source type %s...',
              get_class($config_source))."\n");
        continue;
      }
      $console->writeOut(pht('Migrating file source...')."\n");
      $all_keys = $config_source->getAllKeys();
      foreach ($all_keys as $key => $value) {
        $option = idx($options, $key);
        if (!$option) {
          $console->writeOut(pht('Skipping obsolete option: %s', $key)."\n");
          continue;
        }
        $in_local = $local_config->getKeys(array($option->getKey()));
        if ($in_local) {
          $console->writeOut(pht(
            'Skipping option "%s"; already in local config.', $key)."\n");
          continue;
        }
        $is_locked = $option->getLocked();
        if ($is_locked) {
          $local_config->setKeys(array($option->getKey() => $value));
          $key_count++;
          $console->writeOut(pht(
            'Migrated option "%s" from file to local config.', $key)."\n");
        } else {
          $in_database = $database_config->getKeys(array($option->getKey()));
          if ($in_database) {
            $console->writeOut(pht(
              'Skipping option "%s"; already in database config.', $key)."\n");
            continue;
          } else {
            $config_entry = PhabricatorConfigEntry::loadConfigEntry($key);
            $config_entry->setValue($value);
            $config_entry->save();
            $key_count++;
            $console->writeOut(pht(
              'Migrated option "%s" from file to database config.', $key)."\n");
          }
        }
      }
    }

    $console->writeOut(pht(
      'Done. Migrated %d keys.', $key_count)."\n");
    return 0;
  }

}
