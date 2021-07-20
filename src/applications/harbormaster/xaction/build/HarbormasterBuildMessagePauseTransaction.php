<?php

final class HarbormasterBuildMessagePauseTransaction
  extends HarbormasterBuildMessageTransaction {

  const TRANSACTIONTYPE = 'message/pause';
  const MESSAGETYPE = 'pause';

  public function getHarbormasterBuildMessageName() {
    return pht('Pause Build');
  }

  public function getHarbormasterBuildableMessageName() {
    return pht('Pause Builds');
  }

  public function newConfirmPromptTitle() {
    return pht('Really pause build?');
  }

  public function getHarbormasterBuildableMessageEffect() {
    return pht('Build will pause.');
  }

  public function newConfirmPromptBody() {
    return pht(
      'If you pause this build, work will halt once the current steps '.
      'complete. You can resume the build later.');
  }


  public function getHarbormasterBuildMessageDescription() {
    return pht('Pause the build.');
  }

  public function newBuildableConfirmPromptTitle(
    array $builds,
    array $sendable) {
    return pht(
      'Really pause %s build(s)?',
      phutil_count($builds));
  }

  public function newBuildableConfirmPromptBody(
    array $builds,
    array $sendable) {

    if (count($sendable) === count($builds)) {
      return pht(
        'If you pause all builds, work will halt once the current steps '.
        'complete. You can resume the builds later.');
    } else {
      return pht(
        'You can only pause some builds. Once the current steps complete, '.
        'work will halt on builds you can pause. You can resume the builds '.
        'later.');
    }
  }

  public function getTitle() {
    return pht(
      '%s paused this build.',
      $this->renderAuthor());
  }

  public function getIcon() {
    return 'fa-pause';
  }

  public function getColor() {
    return 'red';
  }

  public function applyInternalEffects($object, $value) {
    $actor = $this->getActor();
    $build = $object;

    $build->setBuildStatus(HarbormasterBuildStatus::STATUS_PAUSED);
  }

  protected function newCanApplyMessageAssertion(
    PhabricatorUser $viewer,
    HarbormasterBuild $build) {

    if ($build->isAutobuild()) {
      throw new HarbormasterMessageException(
        pht('Unable to Pause Build'),
        pht('You can not pause a build that uses an autoplan.'));
    }

    if ($build->isPaused()) {
      throw new HarbormasterMessageException(
        pht('Unable to Pause Build'),
        pht('You can not pause this build because it is already paused.'));
    }

    if ($build->isComplete()) {
      throw new HarbormasterMessageException(
        pht('Unable to Pause Build'),
        pht('You can not pause this build because it has already completed.'));
    }
  }

  protected function newCanSendMessageAssertion(
    PhabricatorUser $viewer,
    HarbormasterBuild $build) {

    if ($build->isPausing()) {
      throw new HarbormasterMessageException(
        pht('Unable to Pause Build'),
        pht('You can not pause this build because it is already pausing.'));
    }

    if ($build->isRestarting()) {
      throw new HarbormasterMessageException(
        pht('Unable to Pause Build'),
        pht('You can not pause this build because it is already restarting.'));
    }

    if ($build->isAborting()) {
      throw new HarbormasterMessageException(
        pht('Unable to Pause Build'),
        pht('You can not pause this build because it is already aborting.'));
    }
  }
}
