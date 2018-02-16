<?php

final class HarbormasterManagementBuildWorkflow
  extends HarbormasterManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('build')
      ->setExamples('**build** [__options__] __buildable__ --plan __id__')
      ->setSynopsis(pht('Run plan __id__ on __buildable__.'))
      ->setArguments(
        array(
          array(
            'name'        => 'plan',
            'param'       => 'id',
            'help'        => pht('ID of build plan to run.'),
          ),
          array(
            'name' => 'background',
            'help' => pht(
              'Submit builds into the build queue normally instead of '.
              'running them in the foreground.'),
          ),
          array(
            'name'        => 'buildable',
            'wildcard'    => true,
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $viewer = $this->getViewer();

    $names = $args->getArg('buildable');
    if (count($names) != 1) {
      throw new PhutilArgumentUsageException(
        pht('Specify exactly one buildable object, by object name.'));
    }

    $name = head($names);

    $buildable = id(new PhabricatorObjectQuery())
      ->setViewer($viewer)
      ->withNames($names)
      ->executeOne();
    if (!$buildable) {
      throw new PhutilArgumentUsageException(
        pht('No such buildable "%s"!', $name));
    }

    if (!($buildable instanceof HarbormasterBuildableInterface)) {
      throw new PhutilArgumentUsageException(
        pht('Object "%s" is not a buildable!', $name));
    }

    $plan_id = $args->getArg('plan');
    if (!$plan_id) {
      throw new PhutilArgumentUsageException(
        pht(
          'Use %s to specify a build plan to run.',
          '--plan'));
    }

    $plan = id(new HarbormasterBuildPlanQuery())
      ->setViewer($viewer)
      ->withIDs(array($plan_id))
      ->executeOne();
    if (!$plan) {
      throw new PhutilArgumentUsageException(
        pht('Build plan "%s" does not exist.', $plan_id));
    }

    if (!$plan->canRunManually()) {
      throw new PhutilArgumentUsageException(
        pht('This build plan can not be run manually.'));
    }

    $console = PhutilConsole::getConsole();

    $buildable = HarbormasterBuildable::initializeNewBuildable($viewer)
      ->setIsManualBuildable(true)
      ->setBuildablePHID($buildable->getHarbormasterBuildablePHID())
      ->setContainerPHID($buildable->getHarbormasterContainerPHID())
      ->save();

    $buildable->sendMessage(
      $viewer,
      HarbormasterMessageType::BUILDABLE_BUILD,
      false);

    $console->writeOut(
      "%s\n",
      pht(
        'Applying plan %s to new buildable %s...',
        $plan->getID(),
        'B'.$buildable->getID()));

    $console->writeOut(
      "\n    %s\n\n",
      PhabricatorEnv::getProductionURI('/B'.$buildable->getID()));

    if (!$args->getArg('background')) {
      PhabricatorWorker::setRunAllTasksInProcess(true);
    }

    if ($viewer->isOmnipotent()) {
      $initiator = id(new PhabricatorHarbormasterApplication())->getPHID();
    } else {
      $initiator =  $viewer->getPHID();
    }
    $buildable->applyPlan($plan, array(), $initiator);

    $console->writeOut("%s\n", pht('Done.'));

    return 0;
  }

}
