<?php

final class PhabricatorRepositoryManagementPullWorkflow
  extends PhabricatorRepositoryManagementWorkflow {

  public function didConstruct() {
    $this
      ->setName('pull')
      ->setExamples('**pull** __repository__ ...')
      ->setSynopsis('Pull __repository__, named by callsign or PHID.')
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
    $names = $args->getArg('repos');
    $repos = PhabricatorRepository::loadAllByPHIDOrCallsign($names);

    if (!$repos) {
      throw new PhutilArgumentUsageException(
        "Specify one or more repositories to pull, by callsign or PHID.");
    }

    $console = PhutilConsole::getConsole();
    foreach ($repos as $repo) {
      $console->writeOut("Pulling '%s'...\n", $repo->getCallsign());

      $daemon = new PhabricatorRepositoryPullLocalDaemon(array());
      $daemon->setVerbose($args->getArg('verbose'));
      $daemon->pullRepository($repo);
    }

    $console->writeOut("Done.\n");

    return 0;
  }

}
