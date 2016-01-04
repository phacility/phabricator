<?php

final class PhabricatorRepositoryManagementDiscoverWorkflow
  extends PhabricatorRepositoryManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('discover')
      ->setExamples('**discover** [__options__] __repository__ ...')
      ->setSynopsis(pht('Discover __repository__.'))
      ->setArguments(
        array(
          array(
            'name'        => 'verbose',
            'help'        => pht('Show additional debugging information.'),
          ),
          array(
            'name'        => 'repair',
            'help'        => pht(
              'Repair a repository with gaps in commit history.'),
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
        pht('Specify one or more repositories to discover.'));
    }

    $console = PhutilConsole::getConsole();
    foreach ($repos as $repo) {
      $console->writeOut(
        "%s\n",
        pht(
          'Discovering "%s"...',
          $repo->getDisplayName()));

      id(new PhabricatorRepositoryDiscoveryEngine())
        ->setRepository($repo)
        ->setVerbose($args->getArg('verbose'))
        ->setRepairMode($args->getArg('repair'))
        ->discoverCommits();
    }

    $console->writeOut("%s\n", pht('Done.'));

    return 0;
  }

}
