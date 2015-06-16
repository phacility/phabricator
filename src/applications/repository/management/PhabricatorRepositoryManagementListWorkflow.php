<?php

final class PhabricatorRepositoryManagementListWorkflow
  extends PhabricatorRepositoryManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('list')
      ->setSynopsis(pht('Show a list of repositories.'))
      ->setArguments(array());
  }

  public function execute(PhutilArgumentParser $args) {
    $console = PhutilConsole::getConsole();

    $repos = id(new PhabricatorRepositoryQuery())
      ->setViewer($this->getViewer())
      ->execute();
    if ($repos) {
      foreach ($repos as $repo) {
        $console->writeOut("%s\n", $repo->getCallsign());
      }
    } else {
      $console->writeErr("%s\n", pht('There are no repositories.'));
    }

    return 0;
  }

}
