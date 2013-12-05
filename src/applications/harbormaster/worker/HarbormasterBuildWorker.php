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
      ->executeOne();
    if (!$build) {
      throw new PhabricatorWorkerPermanentFailureException(
        pht('Invalid build ID "%s".', $id));
    }

    // It's possible for the user to request cancellation before
    // a worker picks up a build.  We check to see if the build
    // is already cancelled, and return if it is.
    if ($build->checkForCancellation()) {
      return;
    }

    try {
      $build->setBuildStatus(HarbormasterBuild::STATUS_BUILDING);
      $build->save();

      $buildable = $build->getBuildable();
      $plan = $build->getBuildPlan();

      $steps = $plan->loadOrderedBuildSteps();

      // Perform the build.
      foreach ($steps as $step) {

        // Create the target at this step.
        // TODO: Support variable artifacts.
        $target = HarbormasterBuildTarget::initializeNewBuildTarget(
          $build,
          $step,
          $build->retrieveVariablesFromBuild());
        $target->save();

        $implementation = $target->getImplementation();
        if (!$implementation->validateSettings()) {
          $build->setBuildStatus(HarbormasterBuild::STATUS_ERROR);
          break;
        }
        $implementation->execute($build, $target);
        if ($build->getBuildStatus() !== HarbormasterBuild::STATUS_BUILDING) {
          break;
        }
        if ($build->checkForCancellation()) {
          break;
        }
      }

      // Check to see if the user requested cancellation.  If they did and
      // we get to here, they might have either cancelled too late, or the
      // step isn't cancellation aware.  In either case we ignore the result
      // and move to a cancelled state.
      $build->checkForCancellation();

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
