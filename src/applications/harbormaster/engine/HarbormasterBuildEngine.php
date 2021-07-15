<?php

/**
 * Moves a build forward by queuing build tasks, canceling or restarting the
 * build, or failing it in response to task failures.
 */
final class HarbormasterBuildEngine extends Phobject {

  private $build;
  private $viewer;
  private $newBuildTargets = array();
  private $artifactReleaseQueue = array();
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
    $viewer = $this->getViewer();
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
      $build->setBuildStatus(HarbormasterBuildStatus::STATUS_ERROR);
      $build->save();

      $lock->unlock();

      $build->releaseAllArtifacts($viewer);

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
        ),
        array(
          'objectPHID' => $target->getPHID(),
        ));
    }

    // If the build changed status, we might need to update the overall status
    // on the buildable.
    $new_status = $build->getBuildStatus();
    if ($new_status != $old_status || $this->shouldForceBuildableUpdate()) {
      $this->updateBuildable($build->getBuildable());
    }

    $this->releaseQueuedArtifacts();

    // If we are no longer building for any reason, release all artifacts.
    if (!$build->isBuilding()) {
      $build->releaseAllArtifacts($viewer);
    }
  }

  private function updateBuild(HarbormasterBuild $build) {
    $viewer = $this->getViewer();

    $content_source = PhabricatorContentSource::newForSource(
      PhabricatorDaemonContentSource::SOURCECONST);

    $acting_phid = $viewer->getPHID();
    if (!$acting_phid) {
      $acting_phid = id(new PhabricatorHarbormasterApplication())->getPHID();
    }

    $editor = $build->getApplicationTransactionEditor()
      ->setActor($viewer)
      ->setActingAsPHID($acting_phid)
      ->setContentSource($content_source)
      ->setContinueOnNoEffect(true)
      ->setContinueOnMissingFields(true);

    $xactions = array();

    $messages = $build->getUnprocessedMessagesForApply();
    foreach ($messages as $message) {
      $message_type = $message->getType();

      $message_xaction =
        HarbormasterBuildMessageTransaction::getTransactionTypeForMessageType(
          $message_type);

      if (!$message_xaction) {
        continue;
      }

      $xactions[] = $build->getApplicationTransactionTemplate()
        ->setAuthorPHID($message->getAuthorPHID())
        ->setTransactionType($message_xaction)
        ->setNewValue($message_type);
    }

    if (!$xactions) {
      if ($build->isPending()) {
        // TODO: This should be a transaction.

        $build->restartBuild($viewer);
        $build->setBuildStatus(HarbormasterBuildStatus::STATUS_BUILDING);
        $build->save();
      }
    }

    if ($xactions) {
      $editor->applyTransactions($build, $xactions);
      $build->markUnprocessedMessagesAsProcessed();
    }

    if ($build->getBuildStatus() == HarbormasterBuildStatus::STATUS_BUILDING) {
      $this->updateBuildSteps($build);
    }
  }

  private function updateBuildSteps(HarbormasterBuild $build) {
    $all_targets = id(new HarbormasterBuildTargetQuery())
      ->setViewer($this->getViewer())
      ->withBuildPHIDs(array($build->getPHID()))
      ->withBuildGenerations(array($build->getBuildGeneration()))
      ->execute();

    $this->updateWaitingTargets($all_targets);

    $targets = mgroup($all_targets, 'getBuildStepPHID');

    $steps = id(new HarbormasterBuildStepQuery())
      ->setViewer($this->getViewer())
      ->withBuildPlanPHIDs(array($build->getBuildPlan()->getPHID()))
      ->execute();
    $steps = mpull($steps, null, 'getPHID');

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
      $build->setBuildStatus(HarbormasterBuildStatus::STATUS_FAILED);
      $build->save();
      return;
    }

    // If every step is complete, we're done with this build. Mark it passed
    // and bail.
    if (count($complete) == count($steps)) {
      $build->setBuildStatus(HarbormasterBuildStatus::STATUS_PASSED);
      $build->save();
      return;
    }

    // Release any artifacts which are not inputs to any remaining build
    // step. We're done with these, so something else is free to use them.
    $ongoing_phids = array_keys($queued + $waiting + $underway);
    $ongoing_steps = array_select_keys($steps, $ongoing_phids);
    $this->releaseUnusedArtifacts($all_targets, $ongoing_steps);

    // Identify all the steps which are ready to run (because all their
    // dependencies are complete).

    $runnable = array();
    foreach ($steps as $step) {
      $dependencies = $step->getStepImplementation()->getDependencies($step);

      if (isset($queued[$step->getPHID()])) {
        $can_run = true;
        foreach ($dependencies as $dependency) {
          if (empty($complete[$dependency])) {
            $can_run = false;
            break;
          }
        }

        if ($can_run) {
          $runnable[] = $step;
        }
      }
    }

    if (!$runnable && !$waiting && !$underway) {
      // This means the build is deadlocked, and the user has configured
      // circular dependencies.
      $build->setBuildStatus(HarbormasterBuildStatus::STATUS_DEADLOCKED);
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
   * Release any artifacts which aren't used by any running or waiting steps.
   *
   * This releases artifacts as soon as they're no longer used. This can be
   * particularly relevant when a build uses multiple hosts since it returns
   * hosts to the pool more quickly.
   *
   * @param list<HarbormasterBuildTarget> Targets in the build.
   * @param list<HarbormasterBuildStep> List of running and waiting steps.
   * @return void
   */
  private function releaseUnusedArtifacts(array $targets, array $steps) {
    assert_instances_of($targets, 'HarbormasterBuildTarget');
    assert_instances_of($steps, 'HarbormasterBuildStep');

    if (!$targets || !$steps) {
      return;
    }

    $target_phids = mpull($targets, 'getPHID');

    $artifacts = id(new HarbormasterBuildArtifactQuery())
      ->setViewer($this->getViewer())
      ->withBuildTargetPHIDs($target_phids)
      ->withIsReleased(false)
      ->execute();
    if (!$artifacts) {
      return;
    }

    // Collect all the artifacts that remaining build steps accept as inputs.
    $must_keep = array();
    foreach ($steps as $step) {
      $inputs = $step->getStepImplementation()->getArtifactInputs();
      foreach ($inputs as $input) {
        $artifact_key = $input['key'];
        $must_keep[$artifact_key] = true;
      }
    }

    // Queue unreleased artifacts which no remaining step uses for immediate
    // release.
    foreach ($artifacts as $artifact) {
      $key = $artifact->getArtifactKey();
      if (isset($must_keep[$key])) {
        continue;
      }

      $this->artifactReleaseQueue[] = $artifact;
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
      ->withReceiverPHIDs(array_keys($waiting_targets))
      ->withConsumed(false)
      ->execute();

    foreach ($messages as $message) {
      $target = $waiting_targets[$message->getReceiverPHID()];

      switch ($message->getType()) {
        case HarbormasterMessageType::MESSAGE_PASS:
          $new_status = HarbormasterBuildTarget::STATUS_PASSED;
          break;
        case HarbormasterMessageType::MESSAGE_FAIL:
          $new_status = HarbormasterBuildTarget::STATUS_FAILED;
          break;
        case HarbormasterMessageType::MESSAGE_WORK:
        default:
          $new_status = null;
          break;
      }

      if ($new_status !== null) {
        $message->setIsConsumed(true);
        $message->save();

        $target->setTargetStatus($new_status);

        if ($target->isComplete()) {
          $target->setDateCompleted(PhabricatorTime::getNow());
        }

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
   public function updateBuildable(HarbormasterBuildable $buildable) {
    $viewer = $this->getViewer();

    $lock_key = 'harbormaster.buildable:'.$buildable->getID();
    $lock = PhabricatorGlobalLock::newLock($lock_key)->lock(15);

    $buildable = id(new HarbormasterBuildableQuery())
      ->setViewer($viewer)
      ->withIDs(array($buildable->getID()))
      ->needBuilds(true)
      ->executeOne();

    $messages = id(new HarbormasterBuildMessageQuery())
      ->setViewer($viewer)
      ->withReceiverPHIDs(array($buildable->getPHID()))
      ->withConsumed(false)
      ->execute();

    $done_preparing = false;
    $update_container = false;
    foreach ($messages as $message) {
      switch ($message->getType()) {
        case HarbormasterMessageType::BUILDABLE_BUILD:
          $done_preparing = true;
          break;
        case HarbormasterMessageType::BUILDABLE_CONTAINER:
          $update_container = true;
          break;
        default:
          break;
      }

      $message
        ->setIsConsumed(true)
        ->save();
    }

    // If we received a "build" command, all builds are scheduled and we can
    // move out of "preparing" into "building".
    if ($done_preparing) {
      if ($buildable->isPreparing()) {
        $buildable
          ->setBuildableStatus(HarbormasterBuildableStatus::STATUS_BUILDING)
          ->save();
      }
    }

    // If we've been informed that the container for the buildable has
    // changed, update it.
    if ($update_container) {
      $object = id(new PhabricatorObjectQuery())
        ->setViewer($viewer)
        ->withPHIDs(array($buildable->getBuildablePHID()))
        ->executeOne();
      if ($object) {
        $buildable
          ->setContainerPHID($object->getHarbormasterContainerPHID())
          ->save();
      }
    }

    $old = clone $buildable;

    // Don't update the buildable status if we're still preparing builds: more
    // builds may still be scheduled shortly, so even if every build we know
    // about so far has passed, that doesn't mean the buildable has actually
    // passed everything it needs to.

    if (!$buildable->isPreparing()) {
      $behavior_key = HarbormasterBuildPlanBehavior::BEHAVIOR_BUILDABLE;
      $behavior = HarbormasterBuildPlanBehavior::getBehavior($behavior_key);

      $key_never = HarbormasterBuildPlanBehavior::BUILDABLE_NEVER;
      $key_building = HarbormasterBuildPlanBehavior::BUILDABLE_IF_BUILDING;

      $all_pass = true;
      $any_fail = false;
      foreach ($buildable->getBuilds() as $build) {
        $plan = $build->getBuildPlan();
        $option = $behavior->getPlanOption($plan);
        $option_key = $option->getKey();

        $is_never = ($option_key === $key_never);
        $is_building = ($option_key === $key_building);

        // If this build "Never" affects the buildable, ignore it.
        if ($is_never) {
          continue;
        }

        // If this build affects the buildable "If Building", but is already
        // complete, ignore it.
        if ($is_building && $build->isComplete()) {
          continue;
        }

        if (!$build->isPassed()) {
          $all_pass = false;
        }

        if ($build->isComplete() && !$build->isPassed()) {
          $any_fail = true;
        }
      }

      if ($any_fail) {
        $new_status = HarbormasterBuildableStatus::STATUS_FAILED;
      } else if ($all_pass) {
        $new_status = HarbormasterBuildableStatus::STATUS_PASSED;
      } else {
        $new_status = HarbormasterBuildableStatus::STATUS_BUILDING;
      }

      $did_update = ($old->getBuildableStatus() !== $new_status);
      if ($did_update) {
        $buildable->setBuildableStatus($new_status);
        $buildable->save();
      }
    }

    $lock->unlock();

    // Don't publish anything if we're still preparing builds.
    if ($buildable->isPreparing()) {
      return;
    }

    $this->publishBuildable($old, $buildable);
  }

  public function publishBuildable(
    HarbormasterBuildable $old,
    HarbormasterBuildable $new) {

    $viewer = $this->getViewer();

    // Publish the buildable. We publish buildables even if they haven't
    // changed status in Harbormaster because applications may care about
    // different things than Harbormaster does. For example, Differential
    // does not care about local lint and unit tests when deciding whether
    // a revision should move out of draft or not.

    // NOTE: We're publishing both automatic and manual buildables. Buildable
    // objects should generally ignore manual buildables, but it's up to them
    // to decide.

    $object = id(new PhabricatorObjectQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($new->getBuildablePHID()))
      ->executeOne();
    if (!$object) {
      return;
    }

    $engine = HarbormasterBuildableEngine::newForObject($object, $viewer);

    $daemon_source = PhabricatorContentSource::newForSource(
      PhabricatorDaemonContentSource::SOURCECONST);

    $harbormaster_phid = id(new PhabricatorHarbormasterApplication())
      ->getPHID();

    $engine
      ->setActingAsPHID($harbormaster_phid)
      ->setContentSource($daemon_source)
      ->publishBuildable($old, $new);
  }

  private function releaseQueuedArtifacts() {
    foreach ($this->artifactReleaseQueue as $key => $artifact) {
      $artifact->releaseArtifact();
      unset($this->artifactReleaseQueue[$key]);
    }
  }

}
