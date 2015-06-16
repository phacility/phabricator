<?php

final class PhabricatorRepositoryManagementMirrorWorkflow
  extends PhabricatorRepositoryManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('mirror')
      ->setExamples('**mirror** [__options__] __repository__ ...')
      ->setSynopsis(
        pht('Push __repository__, named by callsign, to mirrors.'))
      ->setArguments(
        array(
          array(
            'name'        => 'verbose',
            'help'        => pht('Show additional debugging information.'),
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
        pht(
          'Specify one or more repositories to push to mirrors, by callsign.'));
    }

    $console = PhutilConsole::getConsole();
    foreach ($repos as $repo) {
      $console->writeOut(
        "%s\n",
        pht('Pushing "%s" to mirrors...', $repo->getCallsign()));

      $engine = id(new PhabricatorRepositoryMirrorEngine())
        ->setRepository($repo)
        ->setVerbose($args->getArg('verbose'))
        ->pushToMirrors();
    }

    $console->writeOut('%s\b', pht('Done.'));

    return 0;
  }

}
