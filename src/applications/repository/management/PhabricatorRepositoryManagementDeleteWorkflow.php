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
            'name'        => 'force',
            'help'        => 'Do not prompt for confirmation.',
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

    if (!$args->getArg('force')) {
      $console->writeOut("%s\n\n", pht('These repositories will be deleted:'));

      foreach ($repos as $repo) {
        $console->writeOut(
          "  %s %s\n",
          'r'.$repo->getCallsign(),
          $repo->getName());
      }

      $prompt = pht('Permanently delete these repositories?');
      if (!$console->confirm($prompt)) {
        return 1;
      }
    }

    foreach ($repos as $repo) {
      $console->writeOut("Deleting '%s'...\n", $repo->getCallsign());
      $repo->delete();
    }

    $console->writeOut("Done.\n");

    return 0;
  }

}
