<?php

final class HarbormasterWaitForPreviousBuildStepImplementation
  extends HarbormasterBuildStepImplementation {

  public function getName() {
    return pht('Wait for Previous Commits to Build');
  }

  public function getGenericDescription() {
    return pht(
      'Wait for previous commits to finish building the current plan '.
      'before continuing.');
  }

  public function execute(
    HarbormasterBuild $build,
    HarbormasterBuildTarget $build_target) {

    // We can only wait when building against commits.
    $buildable = $build->getBuildable();
    $object = $buildable->getBuildableObject();
    if (!($object instanceof PhabricatorRepositoryCommit)) {
      return;
    }

    // Block until all previous builds of the same build plan have
    // finished.
    $plan = $build->getBuildPlan();

    $existing_logs = id(new HarbormasterBuildLogQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withBuildTargetPHIDs(array($build_target->getPHID()))
      ->execute();

    if ($existing_logs) {
      $log = head($existing_logs);
    } else {
      $log = $build->createLog($build_target, 'waiting', 'blockers');
    }

    $blockers = $this->getBlockers($object, $plan, $build);
    if ($blockers) {
      $log->start();
      $log->append(pht("Blocked by: %s\n", implode(',', $blockers)));
      $log->finalize();
    }

    if ($blockers) {
      throw new PhabricatorWorkerYieldException(15);
    }
  }

  private function getBlockers(
    PhabricatorRepositoryCommit $commit,
    HarbormasterBuildPlan $plan,
    HarbormasterBuild $source) {

    $call = new ConduitCall(
      'diffusion.commitparentsquery',
      array(
        'commit'   => $commit->getCommitIdentifier(),
        'callsign' => $commit->getRepository()->getCallsign(),
      ));
    $call->setUser(PhabricatorUser::getOmnipotentUser());
    $parents = $call->execute();

    $parents = id(new DiffusionCommitQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withRepository($commit->getRepository())
      ->withIdentifiers($parents)
      ->execute();

    $blockers = array();

    $build_objects = array();
    foreach ($parents as $parent) {
      if (!$parent->isImported()) {
        $blockers[] = pht('Commit %s', $parent->getCommitIdentifier());
      } else {
        $build_objects[] = $parent->getPHID();
      }
    }

    if ($build_objects) {
      $buildables = id(new HarbormasterBuildableQuery())
        ->setViewer(PhabricatorUser::getOmnipotentUser())
        ->withBuildablePHIDs($build_objects)
        ->withManualBuildables(false)
        ->execute();
      $buildable_phids = mpull($buildables, 'getPHID');

      if ($buildable_phids) {
        $builds = id(new HarbormasterBuildQuery())
          ->setViewer(PhabricatorUser::getOmnipotentUser())
          ->withBuildablePHIDs($buildable_phids)
          ->withBuildPlanPHIDs(array($plan->getPHID()))
          ->execute();

        foreach ($builds as $build) {
          if (!$build->isComplete()) {
            $blockers[] = pht('Build %d', $build->getID());
          }
        }
      }
    }

    return $blockers;
  }

}
