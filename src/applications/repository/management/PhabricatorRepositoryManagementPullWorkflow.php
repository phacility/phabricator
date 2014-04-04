<?php

final class PhabricatorRepositoryManagementPullWorkflow
  extends PhabricatorRepositoryManagementWorkflow {

  public function didConstruct() {
    $this
      ->setName('pull')
      ->setExamples('**pull** __repository__ ...')
      ->setSynopsis('Pull __repository__, named by callsign.')
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
        "Specify one or more repositories to pull, by callsign.");
    }

    $console = PhutilConsole::getConsole();
    foreach ($repos as $repo) {
      $console->writeOut("Pulling '%s'...\n", $repo->getCallsign());

      id(new PhabricatorRepositoryPullEngine())
        ->setRepository($repo)
        ->setVerbose($args->getArg('verbose'))
        ->pullRepository();
    }

    $console->writeOut("Done.\n");

    return 0;
  }

}
