<?php

final class PhabricatorRepositoryManagementDiscoverWorkflow
  extends PhabricatorRepositoryManagementWorkflow {

  public function didConstruct() {
    $this
      ->setName('discover')
      ->setExamples('**discover** [__options__] __repository__ ...')
      ->setSynopsis('Discover __repository__, named by callsign or PHID.')
      ->setArguments(
        array(
          array(
            'name'        => 'verbose',
            'help'        => 'Show additional debugging information.',
          ),
          array(
            'name'        => 'repair',
            'help'        => 'Repair a repository with gaps in commit '.
                             'history.',
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
        "Specify one or more repositories to discover, by callsign or PHID.");
    }

    $console = PhutilConsole::getConsole();
    foreach ($repos as $repo) {
      $console->writeOut("Discovering '%s'...\n", $repo->getCallsign());

      $daemon = new PhabricatorRepositoryPullLocalDaemon(array());
      $daemon->setVerbose($args->getArg('verbose'));
      $daemon->setRepair($args->getArg('repair'));
      $daemon->discoverRepository($repo);
    }

    $console->writeOut("Done.\n");

    return 0;
  }

}
