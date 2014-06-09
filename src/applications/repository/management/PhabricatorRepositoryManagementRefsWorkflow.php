<?php

final class PhabricatorRepositoryManagementRefsWorkflow
  extends PhabricatorRepositoryManagementWorkflow {

  public function didConstruct() {
    $this
      ->setName('refs')
      ->setExamples('**refs** [__options__] __repository__ ...')
      ->setSynopsis('Update refs in __repository__, named by callsign.')
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
        pht(
          'Specify one or more repositories to update refs for, '.
          'by callsign.'));
    }

    $console = PhutilConsole::getConsole();
    foreach ($repos as $repo) {
      $console->writeOut("Updating refs in '%s'...\n", $repo->getCallsign());

      $engine = id(new PhabricatorRepositoryRefEngine())
        ->setRepository($repo)
        ->setVerbose($args->getArg('verbose'))
        ->updateRefs();
    }

    $console->writeOut("Done.\n");

    return 0;
  }

}
