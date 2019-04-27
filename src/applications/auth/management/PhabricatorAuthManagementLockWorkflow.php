<?php

final class PhabricatorAuthManagementLockWorkflow
  extends PhabricatorAuthManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('lock')
      ->setExamples('**lock**')
      ->setSynopsis(
        pht(
          'Lock authentication provider config, to prevent changes to '.
          'the config without doing **bin/auth unlock**.'));
  }

  public function execute(PhutilArgumentParser $args) {
    $console = PhutilConsole::getConsole();

    $key = 'auth.lock-config';
    $config_entry = PhabricatorConfigEntry::loadConfigEntry($key);
    $config_entry->setValue(true);

    // If the entry has been deleted, resurrect it.
    $config_entry->setIsDeleted(0);

    $config_entry->save();

    echo tsprintf(
      "%s\n",
      pht('Locked the authentication provider configuration.'));
  }
}
