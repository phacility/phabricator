<?php

final class PhabricatorRepositoryManagementRefsWorkflow
  extends PhabricatorRepositoryManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('refs')
      ->setExamples('**refs** [__options__] __repository__ ...')
      ->setSynopsis(pht('Update refs in __repository__, named by callsign.'))
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
          'Specify one or more repositories to update refs for, '.
          'by callsign.'));
    }

    $console = PhutilConsole::getConsole();
    foreach ($repos as $repo) {
      $console->writeOut(
        "%s\n",
        pht("Updating refs in '%s'...", $repo->getCallsign()));

      $engine = id(new PhabricatorRepositoryRefEngine())
        ->setRepository($repo)
        ->setVerbose($args->getArg('verbose'))
        ->updateRefs();
    }

    $console->writeOut("%s\n", pht('Done.'));

    return 0;
  }

}
