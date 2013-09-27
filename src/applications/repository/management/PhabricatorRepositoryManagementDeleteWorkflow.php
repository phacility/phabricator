<?php

final class PhabricatorRepositoryManagementDeleteWorkflow
  extends PhabricatorRepositoryManagementWorkflow {

  public function didConstruct() {
    $this
      ->setName('delete')
      ->setExamples('**delete** __repository__ ...')
      ->setSynopsis('Delete __repository__, named by callsign.')
      ->setArguments(
        array(
          array(
            'name'        => 'verbose',
            'help'        => 'Show additional debugging information.',
          ),
          array(
            'name'        => 'repos',
            'wildcard'    => true,
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $repos = $this->loadRepositories($args, 'repos');

    if (!$repos) {
      throw new PhutilArgumentUsageException(
        "Specify one or more repositories to delete, by callsign.");
    }

    $console = PhutilConsole::getConsole();
    foreach ($repos as $repo) {
      $console->writeOut("Deleting '%s'...\n", $repo->getCallsign());

      $repo->delete();
    }

    $console->writeOut("Done.\n");

    return 0;
  }

}
