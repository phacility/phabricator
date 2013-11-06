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

      $steps = id(new HarbormasterBuildStepQuery())
        ->setViewer(PhabricatorUser::getOmnipotentUser())
        ->withBuildPlanPHIDs(array($plan->getPHID()))
        ->execute();

      // Perform the build.
      foreach ($steps as $step) {
        $implementation = $step->getStepImplementation();
        if (!$implementation->validateSettings()) {
          $build->setBuildStatus(HarbormasterBuild::STATUS_ERROR);
          break;
        }
        $implementation->execute($build);
        if ($build->getBuildStatus() !== HarbormasterBuild::STATUS_BUILDING) {
          break;
        }
      }

      // If we get to here, then the build has finished.  Set it to passed
      // if no build step explicitly set the status.
      if ($build->getBuildStatus() === HarbormasterBuild::STATUS_BUILDING) {
        $build->setBuildStatus(HarbormasterBuild::STATUS_PASSED);
      }
      $build->save();
    } catch (Exception $e) {
      // If any exception is raised, the build is marked as a failure and
      // the exception is re-thrown (this ensures we don't leave builds
      // in an inconsistent state).
      $build->setBuildStatus(HarbormasterBuild::STATUS_ERROR);
      $build->save();
      throw $e;
    }
  }

}
