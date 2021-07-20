<?php

final class HarbormasterBuildMessageResumeTransaction
  extends HarbormasterBuildMessageTransaction {

  const TRANSACTIONTYPE = 'message/resume';
  const MESSAGETYPE = 'resume';

  public function getHarbormasterBuildMessageName() {
    return pht('Resume Build');
  }

  public function getHarbormasterBuildableMessageName() {
    return pht('Resume Builds');
  }

  public function getHarbormasterBuildableMessageEffect() {
    return pht('Build will resume.');
  }

  public function newConfirmPromptTitle() {
    return pht('Really resume build?');
  }

  public function newConfirmPromptBody() {
    return pht(
      'Work will continue on the build. Really resume?');
  }

  public function getHarbormasterBuildMessageDescription() {
    return pht('Resume work on a previously paused build.');
  }

  public function newBuildableConfirmPromptTitle(
    array $builds,
    array $sendable) {
    return pht(
      'Really resume %s build(s)?',
      phutil_count($builds));
  }

  public function newBuildableConfirmPromptBody(
    array $builds,
    array $sendable) {

    if (count($sendable) === count($builds)) {
      return pht(
        'Work will continue on all builds. Really resume?');
    } else {
      return pht(
        'You can only resume some builds. Work will continue on builds '.
        'you have permission to resume.');
    }
  }

  public function getTitle() {
    return pht(
      '%s resumed this build.',
      $this->renderAuthor());
  }

  public function getIcon() {
    return 'fa-play';
  }

  public function applyInternalEffects($object, $value) {
    $actor = $this->getActor();
    $build = $object;

    $build->setBuildStatus(HarbormasterBuildStatus::STATUS_BUILDING);
  }

  protected function newCanApplyMessageAssertion(
    PhabricatorUser $viewer,
    HarbormasterBuild $build) {

    if ($build->isAutobuild()) {
      throw new HarbormasterMessageException(
        pht('Unable to Resume Build'),
        pht(
          'You can not resume a build that uses an autoplan.'));
    }

    if (!$build->isPaused() && !$build->isPausing()) {
      throw new HarbormasterMessageException(
        pht('Unable to Resume Build'),
        pht(
          'You can not resume this build because it is not paused. You can '.
          'only resume a paused build.'));
    }

  }

  protected function newCanSendMessageAssertion(
    PhabricatorUser $viewer,
    HarbormasterBuild $build) {

    if ($build->isResuming()) {
      throw new HarbormasterMessageException(
        pht('Unable to Resume Build'),
        pht(
          'You can not resume this build beacuse it is already resuming.'));
    }

    if ($build->isRestarting()) {
      throw new HarbormasterMessageException(
        pht('Unable to Resume Build'),
        pht('You can not resume this build because it is already restarting.'));
    }

    if ($build->isAborting()) {
      throw new HarbormasterMessageException(
        pht('Unable to Resume Build'),
        pht('You can not resume this build because it is already aborting.'));
    }

  }

}
