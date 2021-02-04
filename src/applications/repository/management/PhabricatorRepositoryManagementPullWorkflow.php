<?php

final class PhabricatorRepositoryManagementPullWorkflow
  extends PhabricatorRepositoryManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('pull')
      ->setExamples('**pull** __repository__ ...')
      ->setSynopsis(pht('Pull __repository__.'))
      ->setArguments(
        array(
          array(
            'name'      => 'verbose',
            'help'      => pht('Show additional debugging information.'),
          ),
          array(
            'name' => 'ignore-locality',
            'help' => pht(
              'Pull even if the repository should not be present on this '.
              'host according to repository cluster configuration.'),
          ),
          array(
            'name'      => 'repos',
            'wildcard'  => true,
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $ignore_locality = (bool)$args->getArg('ignore-locality');

    $repos = $this->loadLocalRepositories($args, 'repos', $ignore_locality);
    if (!$repos) {
      throw new PhutilArgumentUsageException(
        pht('Specify one or more repositories to pull.'));
    }

    $console = PhutilConsole::getConsole();
    foreach ($repos as $repo) {
      $console->writeOut(
        "%s\n",
        pht(
          'Pulling "%s"...',
          $repo->getDisplayName()));

      id(new PhabricatorRepositoryPullEngine())
        ->setRepository($repo)
        ->setVerbose($args->getArg('verbose'))
        ->pullRepository();
    }

    $console->writeOut("%s\n", pht('Done.'));

    return 0;
  }

}
