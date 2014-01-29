<?php

/**
 * Moves a build forward by queuing build tasks, canceling or restarting the
 * build, or failing it in response to task failures.
 */
final class HarbormasterBuildEngine extends Phobject {

  private $build;
  private $viewer;
  private $newBuildTargets = array();

  public function queueNewBuildTarget(HarbormasterBuildTarget $target) {
    $this->newBuildTargets[] = $target;
    return $this;
  }

  public function getNewBuildTargets() {
    return $this->newBuildTargets;
  }

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function getViewer() {
    return $this->viewer;
  }

  public function setBuild(HarbormasterBuild $build) {
    $this->build = $build;
    return $this;
  }

  public function getBuild() {
    return $this->build;
  }

  public function continueBuild() {
    $build = $this->getBuild();

    $lock_key = 'harbormaster.build:'.$build->getID();
    $lock = PhabricatorGlobalLock::newLock($lock_key)->lock(15);

    $build->reload();

    try {
      $this->updateBuild($build);
    } catch (Exception $ex) {
      // If any exception is raised, the build is marked as a failure and the
      // exception is re-thrown (this ensures we don't leave builds in an
      // inconsistent state).
      $build->setBuildStatus(HarbormasterBuild::STATUS_ERROR);
      $build->save();

      $lock->unlock();
      throw $ex;
    }

    $lock->unlock();

    // NOTE: We queue new targets after releasing the lock so that in-process
    // execution via `bin/harbormaster` does not reenter the locked region.
    foreach ($this->getNewBuildTargets() as $target) {
      $task = PhabricatorWorker::scheduleTask(
        'HarbormasterTargetWorker',
        array(
          'targetID' => $target->getID(),
        ));
    }
  }

  private function updateBuild(HarbormasterBuild $build) {
    if (($build->getBuildStatus() == HarbormasterBuild::STATUS_PENDING) ||
        ($build->isRestarting())) {
      $this->destroyBuildTargets($build);
      $build->setBuildStatus(HarbormasterBuild::STATUS_BUILDING);
      $build->save();
    }

    if ($build->isResuming()) {
      $build->setBuildStatus(HarbormasterBuild::STATUS_BUILDING);
      $build->save();
    }

    if ($build->isStopping() && !$build->isComplete()) {
      $build->setBuildStatus(HarbormasterBuild::STATUS_STOPPED);
      $build->save();
    }

    $build->deleteUnprocessedCommands();

    if ($build->getBuildStatus() == HarbormasterBuild::STATUS_BUILDING) {
      $this->updateBuildSteps($build);
    }
  }

  private function destroyBuildTargets(HarbormasterBuild $build) {
    $targets = id(new HarbormasterBuildTargetQuery())
      ->setViewer($this->getViewer())
      ->withBuildPHIDs(array($build->getPHID()))
      ->execute();

    if (!$targets) {
      return;
    }

    $target_phids = mpull($targets, 'getPHID');

    $artifacts = id(new HarbormasterBuildArtifactQuery())
      ->setViewer($this->getViewer())
      ->withBuildTargetPHIDs($target_phids)
      ->execute();

    foreach ($artifacts as $artifact) {
      $artifact->delete();
    }

    foreach ($targets as $target) {
      $target->delete();
    }
  }

  private function updateBuildSteps(HarbormasterBuild $build) {
    $targets = id(new HarbormasterBuildTargetQuery())
      ->setViewer($this->getViewer())
      ->withBuildPHIDs(array($build->getPHID()))
      ->execute();
    $targets = mgroup($targets, 'getBuildStepPHID');

    $steps = id(new HarbormasterBuildStepQuery())
      ->setViewer($this->getViewer())
      ->withBuildPlanPHIDs(array($build->getBuildPlan()->getPHID()))
      ->execute();

    // Identify steps which are complete.

    $complete = array();
    $failed = array();
    $waiting = array();
    foreach ($steps as $step) {
      $step_targets = idx($targets, $step->getPHID(), array());

      if ($step_targets) {
        $is_complete = true;
        foreach ($step_targets as $target) {
          if (!$target->isComplete()) {
            $is_complete = false;
            break;
          }
        }

        $is_failed = false;
        foreach ($step_targets as $target) {
          if ($target->isFailed()) {
            $is_failed = true;
            break;
          }
        }

        $is_waiting = false;
      } else {
        $is_complete = false;
        $is_failed = false;
        $is_waiting = true;
      }

      if ($is_complete) {
        $complete[$step->getPHID()] = true;
      }

      if ($is_failed) {
        $failed[$step->getPHID()] = true;
      }

      if ($is_waiting) {
        $waiting[$step->getPHID()] = true;
      }
    }

    // If any step failed, fail the whole build, then bail.
    if (count($failed)) {
      $build->setBuildStatus(HarbormasterBuild::STATUS_FAILED);
      $build->save();
      return;
    }

    // If every step is complete, we're done with this build. Mark it passed
    // and bail.
    if (count($complete) == count($steps)) {
      $build->setBuildStatus(HarbormasterBuild::STATUS_PASSED);
      $build->save();
      return;
    }

    // Identify all the steps which are ready to run (because all their
    // depdendencies are complete).

    $previous_step = null;
    $runnable = array();
    foreach ($steps as $step) {
      // TODO: For now, we're hard coding sequential dependencies into build
      // steps. In the future, we can be smart about this instead.

      if ($previous_step) {
        $dependencies = array($previous_step);
      } else {
        $dependencies = array();
      }

      if (isset($waiting[$step->getPHID()])) {
        $can_run = true;
        foreach ($dependencies as $dependency) {
          if (empty($complete[$dependency->getPHID()])) {
            $can_run = false;
            break;
          }
        }

        if ($can_run) {
          $runnable[] = $step;
        }
      }

      $previous_step = $step;
    }

    if (!$runnable) {
      // TODO: This means the build is deadlocked, probably? It should not
      // normally be possible, but we should communicate it more clearly.
      $build->setBuildStatus(HarbormasterBuild::STATUS_FAILED);
      $build->save();
      return;
    }

    foreach ($runnable as $runnable_step) {
      $target = HarbormasterBuildTarget::initializeNewBuildTarget(
        $build,
        $runnable_step,
        $build->retrieveVariablesFromBuild());
      $target->save();

      $this->queueNewBuildTarget($target);
    }
  }

}
