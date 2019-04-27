<?php

final class PhabricatorAuthManagementUnlockWorkflow
  extends PhabricatorAuthManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('unlock')
      ->setExamples('**unlock**')
      ->setSynopsis(
        pht(
          'Unlock the authentication provider config, to make it possible '.
          'to edit the config using the web UI. Make sure to do '.
          '**bin/auth lock** when done editing the configuration.'));
  }

  public function execute(PhutilArgumentParser $args) {
    $console = PhutilConsole::getConsole();

    $key = 'auth.lock-config';
    $config_entry = PhabricatorConfigEntry::loadConfigEntry($key);
    $config_entry->setValue(false);

    // If the entry has been deleted, resurrect it.
    $config_entry->setIsDeleted(0);

    $config_entry->save();

    echo tsprintf(
      "%s\n",
      pht('Unlocked the authentication provider configuration.'));
  }
}
