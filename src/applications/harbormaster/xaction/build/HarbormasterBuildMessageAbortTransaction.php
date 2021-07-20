<?php

final class HarbormasterBuildMessageAbortTransaction
  extends HarbormasterBuildMessageTransaction {

  const TRANSACTIONTYPE = 'message/abort';
  const MESSAGETYPE = 'abort';

  public function getHarbormasterBuildMessageName() {
    return pht('Abort Build');
  }

  public function getHarbormasterBuildableMessageName() {
    return pht('Abort Builds');
  }

  public function newConfirmPromptTitle() {
    return pht('Really abort build?');
  }

  public function getHarbormasterBuildableMessageEffect() {
    return pht('Build will abort.');
  }

  public function newConfirmPromptBody() {
    return pht(
      'Progress on this build will be discarded. Really abort build?');
  }

  public function getHarbormasterBuildMessageDescription() {
    return pht('Abort the build, discarding progress.');
  }

  public function newBuildableConfirmPromptTitle(
    array $builds,
    array $sendable) {
    return pht(
      'Really abort %s build(s)?',
      phutil_count($builds));
  }

  public function newBuildableConfirmPromptBody(
    array $builds,
    array $sendable) {

    if (count($sendable) === count($builds)) {
      return pht(
        'If you abort all builds, work will halt immediately. Work '.
        'will be discarded, and builds must be completely restarted.');
    } else {
      return pht(
        'You can only abort some builds. Work will halt immediately on '.
        'builds you can abort. Progress will be discarded, and builds must '.
        'be completely restarted if you want them to complete.');
    }
  }

  public function getTitle() {
    return pht(
      '%s aborted this build.',
      $this->renderAuthor());
  }

  public function getIcon() {
    return 'fa-exclamation-triangle';
  }

  public function getColor() {
    return 'red';
  }

  public function applyInternalEffects($object, $value) {
    $actor = $this->getActor();
    $build = $object;

    $build->setBuildStatus(HarbormasterBuildStatus::STATUS_ABORTED);
  }

  public function applyExternalEffects($object, $value) {
    $actor = $this->getActor();
    $build = $object;

    $build->releaseAllArtifacts($actor);
  }

  protected function newCanApplyMessageAssertion(
    PhabricatorUser $viewer,
    HarbormasterBuild $build) {

    if ($build->isAutobuild()) {
      throw new HarbormasterMessageException(
        pht('Unable to Abort Build'),
        pht(
          'You can not abort a build that uses an autoplan.'));
    }

    if ($build->isComplete()) {
      throw new HarbormasterMessageException(
        pht('Unable to Abort Build'),
        pht(
          'You can not abort this biuld because it is already complete.'));
    }
  }

  protected function newCanSendMessageAssertion(
    PhabricatorUser $viewer,
    HarbormasterBuild $build) {

    if ($build->isAborting()) {
      throw new HarbormasterMessageException(
        pht('Unable to Abort Build'),
        pht(
          'You can not abort this build because it is already aborting.'));
    }
  }

}
