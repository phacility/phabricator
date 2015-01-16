<?php

final class PhabricatorFactManagementListWorkflow
  extends PhabricatorFactManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('list')
      ->setSynopsis(pht('Show a list of fact engines.'))
      ->setArguments(array());
  }

  public function execute(PhutilArgumentParser $args) {
    $console = PhutilConsole::getConsole();

    $engines = PhabricatorFactEngine::loadAllEngines();
    foreach ($engines as $engine) {
      $console->writeOut("%s\n", get_class($engine));
    }

    return 0;
  }

}
