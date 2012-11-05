<?php

final class PhabricatorFilesManagementEnginesWorkflow
  extends PhabricatorFilesManagementWorkflow {

  public function didConstruct() {
    $this
      ->setName('engines')
      ->setSynopsis('List available storage engines.')
      ->setArguments(array());
  }

  public function execute(PhutilArgumentParser $args) {
    $console = PhutilConsole::getConsole();

    $engines = PhabricatorFile::buildAllEngines();
    if (!$engines) {
      throw new Exception("No storage engines are available.");
    }

    foreach ($engines as $engine) {
      $console->writeOut(
        "%s\n",
        $engine->getEngineIdentifier());
    }

    return 0;
  }

}
