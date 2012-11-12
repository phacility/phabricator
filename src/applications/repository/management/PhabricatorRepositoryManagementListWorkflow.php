<?php

final class PhabricatorRepositoryManagementListWorkflow
  extends PhabricatorRepositoryManagementWorkflow {

  public function didConstruct() {
    $this
      ->setName('list')
      ->setSynopsis('Show a list of repositories.')
      ->setArguments(array());
  }

  public function execute(PhutilArgumentParser $args) {
    $console = PhutilConsole::getConsole();

    $repos = id(new PhabricatorRepository())->loadAll();
    if ($repos) {
      foreach ($repos as $repo) {
        $console->writeOut("%s\n", $repo->getCallsign());
      }
    } else {
      $console->writeErr("%s\n", 'There are no repositories.');
    }

    return 0;
  }

}
