<?php

final class HarbormasterManagementUpdateWorkflow
  extends HarbormasterManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('update')
      ->setExamples('**update** [__options__] __buildable__')
      ->setSynopsis(pht('Explicitly update the builds for __buildable__.'))
      ->setArguments(
        array(
          array(
            'name' => 'build',
            'param' => 'id',
            'help' => pht('Update only this build.'),
          ),
          array(
            'name' => 'force',
            'help' => pht(
              'Force the buildable to update even if no build status '.
              'changes occur during normal update.'),
          ),
          array(
            'name' => 'background',
            'help' => pht(
              'If updating generates tasks, queue them for the daemons '.
              'instead of executing them in this process.'),
          ),
          array(
            'name'        => 'buildable',
            'wildcard'    => true,
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $viewer = $this->getViewer();

    $force_update = $args->getArg('force');

    $names = $args->getArg('buildable');
    if (count($names) != 1) {
      throw new PhutilArgumentUsageException(
        pht('Specify exactly one buildable, by object name.'));
    }

    $buildable = id(new PhabricatorObjectQuery())
      ->setViewer($viewer)
      ->withNames($names)
      ->executeOne();

    if (!$buildable) {
      throw new PhutilArgumentUsageException(
        pht('No such buildable "%s"!', head($names)));
    }

    if (!($buildable instanceof HarbormasterBuildable)) {
      throw new PhutilArgumentUsageException(
        pht('Object "%s" is not a Harbormaster Buildable!', head($names)));
    }

    // Reload the buildable directly to get builds.
    $buildable = id(new HarbormasterBuildableQuery())
      ->setViewer($viewer)
      ->withIDs(array($buildable->getID()))
      ->needBuilds(true)
      ->executeOne();

    $builds = $buildable->getBuilds();
    $builds = mpull($builds, null, 'getID');

    $build_id = $args->getArg('build');
    if ($build_id) {
      $builds = array_select_keys($builds, array($build_id));
      if (!$builds) {
        throw new PhutilArgumentUsageException(
          pht(
            'The specified buildable does not have a build with ID "%s".',
            $build_id));
      }
    }

    $console = PhutilConsole::getConsole();

    if (!$args->getArg('background')) {
      PhabricatorWorker::setRunAllTasksInProcess(true);
    }

    foreach ($builds as $build) {
      $console->writeOut(
        "%s\n",
        pht(
          'Updating build %d of buildable %s...',
          $build->getID(),
          $buildable->getMonogram()));

      $engine = id(new HarbormasterBuildEngine())
        ->setViewer($viewer)
        ->setBuild($build);

      if ($force_update) {
        $engine->setForceBuildableUpdate(true);
      }

      $engine->continueBuild();
    }

    $console->writeOut("%s\n", pht('Done.'));

    return 0;
  }

}
