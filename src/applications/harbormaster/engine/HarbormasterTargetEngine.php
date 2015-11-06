<?php

final class HarbormasterTargetEngine extends Phobject {

  private $viewer;
  private $object;
  private $autoTargetKeys;

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function getViewer() {
    return $this->viewer;
  }

  public function setObject(HarbormasterBuildableInterface $object) {
    $this->object = $object;
    return $this;
  }

  public function getObject() {
    return $this->object;
  }

  public function setAutoTargetKeys(array $auto_keys) {
    $this->autoTargetKeys = $auto_keys;
    return $this;
  }

  public function getAutoTargetKeys() {
    return $this->autoTargetKeys;
  }

  public function buildTargets() {
    $object = $this->getObject();
    $viewer = $this->getViewer();

    $step_map = $this->generateBuildStepMap($this->getAutoTargetKeys());

    $buildable = HarbormasterBuildable::createOrLoadExisting(
      $viewer,
      $object->getHarbormasterBuildablePHID(),
      $object->getHarbormasterContainerPHID());

    $target_map = $this->generateBuildTargetMap($buildable, $step_map);

    return $target_map;
  }


  /**
   * Get a map of the @{class:HarbormasterBuildStep} objects for a list of
   * autotarget keys.
   *
   * This method creates the steps if they do not yet exist.
   *
   * @param list<string> Autotarget keys, like `"core.arc.lint"`.
   * @return map<string, object> Map of keys to step objects.
   */
  private function generateBuildStepMap(array $autotargets) {
    $viewer = $this->getViewer();

    $autosteps = $this->getAutosteps($autotargets);
    $autosteps = mgroup($autosteps, 'getBuildStepAutotargetPlanKey');

    $plans = id(new HarbormasterBuildPlanQuery())
      ->setViewer($viewer)
      ->withPlanAutoKeys(array_keys($autosteps))
      ->needBuildSteps(true)
      ->execute();
    $plans = mpull($plans, null, 'getPlanAutoKey');

    // NOTE: When creating the plan and steps, we save the autokeys as the
    // names. These won't actually be shown in the UI, but make the data more
    // consistent for secondary consumers like typeaheads.

    $step_map = array();
    foreach ($autosteps as $plan_key => $steps) {
      $plan = idx($plans, $plan_key);
      if (!$plan) {
        $plan = HarbormasterBuildPlan::initializeNewBuildPlan($viewer)
          ->setName($plan_key)
          ->setPlanAutoKey($plan_key);
      }

      $current = $plan->getBuildSteps();
      $current = mpull($current, null, 'getStepAutoKey');
      $new_steps = array();

      foreach ($steps as $step_key => $step) {
        if (isset($current[$step_key])) {
          $step_map[$step_key] = $current[$step_key];
          continue;
        }

        $new_step = HarbormasterBuildStep::initializeNewStep($viewer)
          ->setName($step_key)
          ->setClassName(get_class($step))
          ->setStepAutoKey($step_key);

        $new_steps[$step_key] = $new_step;
      }

      if ($new_steps) {
        $plan->openTransaction();
          if (!$plan->getPHID()) {
            $plan->save();
          }
          foreach ($new_steps as $step_key => $step) {
            $step->setBuildPlanPHID($plan->getPHID());
            $step->save();

            $step->attachBuildPlan($plan);
            $step_map[$step_key] = $step;
          }
        $plan->saveTransaction();
      }
    }

    return $step_map;
  }


  /**
   * Get all of the @{class:HarbormasterBuildStepImplementation} objects for
   * a list of autotarget keys.
   *
   * @param list<string> Autotarget keys, like `"core.arc.lint"`.
   * @return map<string, object> Map of keys to implementations.
   */
  private function getAutosteps(array $autotargets) {
    $all_steps = HarbormasterBuildStepImplementation::getImplementations();
    $all_steps = mpull($all_steps, null, 'getBuildStepAutotargetStepKey');

    // Make sure all the targets really exist.
    foreach ($autotargets as $autotarget) {
      if (empty($all_steps[$autotarget])) {
        throw new Exception(
          pht(
            'No build step provides autotarget "%s"!',
            $autotarget));
      }
    }

    return array_select_keys($all_steps, $autotargets);
  }


  /**
   * Get a list of @{class:HarbormasterBuildTarget} objects for a list of
   * autotarget keys.
   *
   * If some targets or builds do not exist, they are created.
   *
   * @param HarbormasterBuildable A buildable.
   * @param map<string, object> Map of keys to steps.
   * @return map<string, object> Map of keys to targets.
   */
  private function generateBuildTargetMap(
    HarbormasterBuildable $buildable,
    array $step_map) {

    $viewer = $this->getViewer();
    $initiator_phid = null;
    if (!$viewer->isOmnipotent()) {
      $initiator_phid = $viewer->getPHID();
    }
    $plan_map = mgroup($step_map, 'getBuildPlanPHID');

    $builds = id(new HarbormasterBuildQuery())
      ->setViewer($viewer)
      ->withBuildablePHIDs(array($buildable->getPHID()))
      ->withBuildPlanPHIDs(array_keys($plan_map))
      ->needBuildTargets(true)
      ->execute();

    $autobuilds = array();
    foreach ($builds as $build) {
      $plan_key = $build->getBuildPlan()->getPlanAutoKey();
      $autobuilds[$plan_key] = $build;
    }

    $new_builds = array();
    foreach ($plan_map as $plan_phid => $steps) {
      $plan = head($steps)->getBuildPlan();
      $plan_key = $plan->getPlanAutoKey();

      $build = idx($autobuilds, $plan_key);
      if ($build) {
        // We already have a build for this set of targets, so we don't need
        // to do any work. (It's possible the build is an older build that
        // doesn't have all of the right targets if new autotargets were
        // recently introduced, but we don't currently try to construct them.)
        continue;
      }

      // NOTE: Normally, `applyPlan()` does not actually generate targets.
      // We need to apply the plan in-process to perform target generation.
      // This is fine as long as autotargets are empty containers that don't
      // do any work, which they always should be.

      PhabricatorWorker::setRunAllTasksInProcess(true);
      try {

        // NOTE: We might race another process here to create the same build
        // with the same `planAutoKey`. The database will prevent this and
        // using autotargets only currently makes sense if you just created the
        // resource and "own" it, so we don't try to handle this, but may need
        // to be more careful here if use of autotargets expands.

        $build = $buildable->applyPlan($plan, array(), $initiator_phid);
        PhabricatorWorker::setRunAllTasksInProcess(false);
      } catch (Exception $ex) {
        PhabricatorWorker::setRunAllTasksInProcess(false);
        throw $ex;
      }

      $new_builds[] = $build;
    }

    if ($new_builds) {
      $all_targets = id(new HarbormasterBuildTargetQuery())
        ->setViewer($viewer)
        ->withBuildPHIDs(mpull($new_builds, 'getPHID'))
        ->execute();
    } else {
      $all_targets = array();
    }

    foreach ($builds as $build) {
      foreach ($build->getBuildTargets() as $target) {
        $all_targets[] = $target;
      }
    }

    $target_map = array();
    foreach ($all_targets as $target) {
      $target_key = $target
        ->getImplementation()
        ->getBuildStepAutotargetStepKey();
      if (!$target_key) {
        continue;
      }
      $target_map[$target_key] = $target;
    }

    $target_map = array_select_keys($target_map, array_keys($step_map));

    return $target_map;
  }


}
