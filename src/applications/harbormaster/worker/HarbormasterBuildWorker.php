<?php

/**
 * Run builds
 */
final class HarbormasterBuildWorker extends PhabricatorWorker {

  public function getRequiredLeaseTime() {
    return 60 * 60 * 24;
  }

  public function doWork() {
    $data = $this->getTaskData();
    $id = idx($data, 'buildID');

    // Get a reference to the build.
    $build = id(new HarbormasterBuildQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withBuildStatuses(array(HarbormasterBuild::STATUS_PENDING))
      ->withIDs(array($id))
      ->needBuildPlans(true)
      ->executeOne();
    if (!$build) {
      throw new PhabricatorWorkerPermanentFailureException(
        pht('Invalid build ID "%s".', $id));
    }

    try {
      $build->setBuildStatus(HarbormasterBuild::STATUS_BUILDING);
      $build->save();

      $buildable = $build->getBuildable();
      $plan = $build->getBuildPlan();

      // TODO: Do the actual build here.
      sleep(15);

      // If we get to here, then the build has passed.
      $build->setBuildStatus(HarbormasterBuild::STATUS_PASSED);
      $build->save();
    } catch (Exception $e) {
      // If any exception is raised, the build is marked as a failure and
      // the exception is re-thrown (this ensures we don't leave builds
      // in an inconsistent state).
      $build->setBuildStatus(HarbormasterBuild::STATUS_FAILED);
      $build->save();
      throw $e;
    }
  }

}
