<?php

final class HarbormasterBuildMessageRestartTransaction
  extends HarbormasterBuildMessageTransaction {

  const TRANSACTIONTYPE = 'message/restart';
  const MESSAGETYPE = 'restart';

  public function getHarbormasterBuildMessageName() {
    return pht('Restart Build');
  }

  public function getHarbormasterBuildableMessageName() {
    return pht('Restart Builds');
  }

  public function getHarbormasterBuildableMessageEffect() {
    return pht('Build will restart.');
  }

  public function newConfirmPromptTitle() {
    return pht('Really restart build?');
  }

  public function newConfirmPromptBody() {
    return pht(
      'Progress on this build will be discarded and the build will restart. '.
      'Side effects of the build will occur again. Really restart build?');
  }


  public function getHarbormasterBuildMessageDescription() {
    return pht('Restart the build, discarding all progress.');
  }

  public function newBuildableConfirmPromptTitle(
    array $builds,
    array $sendable) {
    return pht(
      'Really restart %s build(s)?',
      phutil_count($builds));
  }

  public function newBuildableConfirmPromptBody(
    array $builds,
    array $sendable) {

    if (count($sendable) === count($builds)) {
      return pht(
        'All builds will restart.');
    } else {
      return pht(
        'You can only restart some builds.');
    }
  }

  public function newBuildableConfirmPromptWarnings(
    array $builds,
    array $sendable) {

    $building = false;
    foreach ($sendable as $build) {
      if ($build->isBuilding()) {
        $building = true;
        break;
      }
    }

    $warnings = array();

    if ($building) {
      $warnings[] = pht(
        'Progress on running builds will be discarded.');
    }

    if ($sendable) {
      $warnings[] = pht(
        'When a build is restarted, side effects associated with '.
        'the build may occur again.');
    }

    return $warnings;
  }

  public function getTitle() {
    return pht(
      '%s restarted this build.',
      $this->renderAuthor());
  }

  public function getIcon() {
    return 'fa-repeat';
  }

  public function applyInternalEffects($object, $value) {
    $actor = $this->getActor();
    $build = $object;

    $build->restartBuild($actor);
    $build->setBuildStatus(HarbormasterBuildStatus::STATUS_BUILDING);
  }

  protected function newCanApplyMessageAssertion(
    PhabricatorUser $viewer,
    HarbormasterBuild $build) {

    if ($build->isAutobuild()) {
      throw new HarbormasterMessageException(
        pht('Can Not Restart Autobuild'),
        pht(
          'This build can not be restarted because it is an automatic '.
          'build.'));
    }

    $restartable = HarbormasterBuildPlanBehavior::BEHAVIOR_RESTARTABLE;
    $plan = $build->getBuildPlan();

    // See T13526. Users who can't see the "BuildPlan" can end up here with
    // no object. This is highly questionable.
    if (!$plan) {
      throw new HarbormasterMessageException(
        pht('No Build Plan Permission'),
        pht(
          'You can not restart this build because you do not have '.
          'permission to access the build plan.'));
    }

    $option = HarbormasterBuildPlanBehavior::getBehavior($restartable)
      ->getPlanOption($plan);
    $option_key = $option->getKey();

    $never_restartable = HarbormasterBuildPlanBehavior::RESTARTABLE_NEVER;
    $is_never = ($option_key === $never_restartable);
    if ($is_never) {
      throw new HarbormasterMessageException(
        pht('Build Plan Prevents Restart'),
        pht(
          'This build can not be restarted because the build plan is '.
          'configured to prevent the build from restarting.'));
    }

    $failed_restartable = HarbormasterBuildPlanBehavior::RESTARTABLE_IF_FAILED;
    $is_failed = ($option_key === $failed_restartable);
    if ($is_failed) {
      if (!$this->isFailed()) {
        throw new HarbormasterMessageException(
          pht('Only Restartable if Failed'),
          pht(
            'This build can not be restarted because the build plan is '.
            'configured to prevent the build from restarting unless it '.
            'has failed, and it has not failed.'));
      }
    }

  }

  protected function newCanSendMessageAssertion(
    PhabricatorUser $viewer,
    HarbormasterBuild $build) {

    if ($build->isRestarting()) {
      throw new HarbormasterMessageException(
        pht('Already Restarting'),
        pht(
          'This build is already restarting. You can not reissue a restart '.
          'command to a restarting build.'));
    }

  }

}
