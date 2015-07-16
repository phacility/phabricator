<?php

final class PhabricatorRepositoryManagementListPathsWorkflow
  extends PhabricatorRepositoryManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('list-paths')
      ->setSynopsis(pht('List repository local paths.'))
      ->setArguments(array());
  }

  public function execute(PhutilArgumentParser $args) {
    $console = PhutilConsole::getConsole();

    $repos = id(new PhabricatorRepositoryQuery())
      ->setViewer($this->getViewer())
      ->execute();
    if (!$repos) {
      $console->writeErr("%s\n", pht('There are no repositories.'));
      return 0;
    }

    $table = id(new PhutilConsoleTable())
      ->addColumn(
        'monogram',
        array(
          'title' => pht('Repository'),
        ))
      ->addColumn(
        'path',
        array(
          'title' => pht('Path'),
        ))
      ->setBorders(true);

    foreach ($repos as $repo) {
      $table->addRow(
        array(
          'monogram' => $repo->getMonogram(),
          'path' => $repo->getLocalPath(),
        ));
    }

    $table->draw();

    return 0;
  }

}
