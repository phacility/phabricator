<?php

final class PhabricatorRepositoryManagementMirrorWorkflow
  extends PhabricatorRepositoryManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('mirror')
      ->setExamples('**mirror** [__options__] __repository__ ...')
      ->setSynopsis(
        pht('Push __repository__ to mirrors.'))
      ->setArguments(
        array(
          array(
            'name' => 'verbose',
            'help' => pht('Show additional debugging information.'),
          ),
          array(
            'name' => 'repos',
            'wildcard' => true,
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $repos = $this->loadLocalRepositories($args, 'repos');

    if (!$repos) {
      throw new PhutilArgumentUsageException(
        pht(
          'Specify one or more repositories to push to mirrors.'));
    }

    foreach ($repos as $repo) {
      echo tsprintf(
        "%s\n",
        pht(
          'Pushing "%s" to mirrors...',
          $repo->getDisplayName()));

      $engine = id(new PhabricatorRepositoryMirrorEngine())
        ->setRepository($repo)
        ->setVerbose($args->getArg('verbose'))
        ->pushToMirrors();
    }

    echo tsprintf(
      "%s\n",
      pht('Done.'));

    return 0;
  }

}
