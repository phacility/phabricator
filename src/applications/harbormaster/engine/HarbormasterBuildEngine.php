<?php

/**
 * Moves a build forward by queuing build tasks, canceling or restarting the
 * build, or failing it in response to task failures.
 */
final class HarbormasterBuildEngine extends Phobject {

  private $build;
  private $viewer;
  private $newBuildTargets = array();
  private $forceBuildableUpdate;

  public function setForceBuildableUpdate($force_buildable_update) {
    $this->forceBuildableUpdate = $force_buildable_update;
    return $this;
  }

  public function shouldForceBuildableUpdate() {
    return $this->forceBuildableUpdate;
  }

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
    $old_status = $build->getBuildStatus();

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

    // If the build changed status, we might need to update the overall status
    // on the buildable.
    $new_status = $build->getBuildStatus();
    if ($new_status != $old_status || $this->shouldForceBuildableUpdate()) {
      $this->updateBuildable($build->getBuildable());
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

    $this->updateWaitingTargets($targets);

    $targets = mgroup($targets, 'getBuildStepPHID');

    $steps = id(new HarbormasterBuildStepQuery())
      ->setViewer($this->getViewer())
      ->withBuildPlanPHIDs(array($build->getBuildPlan()->getPHID()))
      ->execute();

    // Identify steps which are in various states.

    $queued = array();
    $underway = array();
    $waiting = array();
    $complete = array();
    $failed = array();
    foreach ($steps as $step) {
      $step_targets = idx($targets, $step->getPHID(), array());

      if ($step_targets) {
        $is_queued = false;

        $is_underway = false;
        foreach ($step_targets as $target) {
          if ($target->isUnderway()) {
            $is_underway = true;
            break;
          }
        }

        $is_waiting = false;
        foreach ($step_targets as $target) {
          if ($target->isWaiting()) {
            $is_waiting = true;
            break;
          }
        }

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
      } else {
        $is_queued = true;
        $is_underway = false;
        $is_waiting = false;
        $is_complete = false;
        $is_failed = false;
      }

      if ($is_queued) {
        $queued[$step->getPHID()] = true;
      }

      if ($is_underway) {
        $underway[$step->getPHID()] = true;
      }

      if ($is_waiting) {
        $waiting[$step->getPHID()] = true;
      }

      if ($is_complete) {
        $complete[$step->getPHID()] = true;
      }

      if ($is_failed) {
        $failed[$step->getPHID()] = true;
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

      if (isset($queued[$step->getPHID()])) {
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

    if (!$runnable && !$waiting && !$underway) {
      // TODO: This means the build is deadlocked, probably? It should not
      // normally be possible yet, but we should communicate it more clearly.
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


  /**
   * Process messages which were sent to these targets, kicking applicable
   * targets out of "Waiting" and into either "Passed" or "Failed".
   *
   * @param list<HarbormasterBuildTarget> List of targets to process.
   * @return void
   */
  private function updateWaitingTargets(array $targets) {
    assert_instances_of($targets, 'HarbormasterBuildTarget');

    // We only care about messages for targets which are actually in a waiting
    // state.
    $waiting_targets = array();
    foreach ($targets as $target) {
      if ($target->isWaiting()) {
        $waiting_targets[$target->getPHID()] = $target;
      }
    }

    if (!$waiting_targets) {
      return;
    }

    $messages = id(new HarbormasterBuildMessageQuery())
      ->setViewer($this->getViewer())
      ->withBuildTargetPHIDs(array_keys($waiting_targets))
      ->withConsumed(false)
      ->execute();

    foreach ($messages as $message) {
      $target = $waiting_targets[$message->getBuildTargetPHID()];

      $new_status = null;
      switch ($message->getType()) {
        case 'pass':
          $new_status = HarbormasterBuildTarget::STATUS_PASSED;
          break;
        case 'fail':
          $new_status = HarbormasterBuildTarget::STATUS_FAILED;
          break;
      }

      if ($new_status !== null) {
        $message->setIsConsumed(true);
        $message->save();

        $target->setTargetStatus($new_status);
        $target->save();
      }
    }
  }


  /**
   * Update the overall status of the buildable this build is attached to.
   *
   * After a build changes state (for example, passes or fails) it may affect
   * the overall state of the associated buildable. Compute the new aggregate
   * state and save it on the buildable.
   *
   * @param   HarbormasterBuild The buildable to update.
   * @return  void
   */
  private function updateBuildable(HarbormasterBuildable $buildable) {
    $viewer = $this->getViewer();

    $lock_key = 'harbormaster.buildable:'.$buildable->getID();
    $lock = PhabricatorGlobalLock::newLock($lock_key)->lock(15);

    $buildable = id(new HarbormasterBuildableQuery())
      ->setViewer($viewer)
      ->withIDs(array($buildable->getID()))
      ->needBuilds(true)
      ->executeOne();

    $all_pass = true;
    $any_fail = false;
    foreach ($buildable->getBuilds() as $build) {
      if ($build->getBuildStatus() != HarbormasterBuild::STATUS_PASSED) {
        $all_pass = false;
      }
      if ($build->getBuildStatus() == HarbormasterBuild::STATUS_FAILED ||
          $build->getBuildStatus() == HarbormasterBuild::STATUS_ERROR) {
        $any_fail = true;
      }
    }

    if ($any_fail) {
      $new_status = HarbormasterBuildable::STATUS_FAILED;
    } else if ($all_pass) {
      $new_status = HarbormasterBuildable::STATUS_PASSED;
    } else {
      $new_status = HarbormasterBuildable::STATUS_BUILDING;
    }

    $old_status = $buildable->getBuildableStatus();
    $did_update = ($old_status != $new_status);
    if ($did_update) {
      $buildable->setBuildableStatus($new_status);
      $buildable->save();
    }

    $lock->unlock();

    // If we changed the buildable status, try to post a transaction to the
    // object about it. We can safely do this outside of the locked region.

    // NOTE: We only post transactions for automatic buildables, not for
    // manual ones: manual builds are test builds, whoever is doing tests
    // can look at the results themselves, and other users generally don't
    // care about the outcome.

    if ($did_update && !$buildable->getIsManualBuildable()) {

      $object = id(new PhabricatorObjectQuery())
        ->setViewer($viewer)
        ->withPHIDs(array($buildable->getBuildablePHID()))
        ->executeOne();

      if ($object instanceof PhabricatorApplicationTransactionInterface) {
        $template = $object->getApplicationTransactionTemplate();
        if ($template) {
          $template
            ->setTransactionType(PhabricatorTransactions::TYPE_BUILDABLE)
            ->setMetadataValue(
              'harbormaster:buildablePHID',
              $buildable->getPHID())
            ->setOldValue($old_status)
            ->setNewValue($new_status);

          $harbormaster_phid = id(new PhabricatorApplicationHarbormaster())
            ->getPHID();

          $daemon_source = PhabricatorContentSource::newForSource(
            PhabricatorContentSource::SOURCE_DAEMON,
            array());

          $editor = $object->getApplicationTransactionEditor()
            ->setActor($viewer)
            ->setActingAsPHID($harbormaster_phid)
            ->setContentSource($daemon_source)
            ->setContinueOnNoEffect(true)
            ->setContinueOnMissingFields(true);

          $editor->applyTransactions(
            $object->getApplicationTransactionObject(),
            array($template));
        }
      }
    }

  }

}
