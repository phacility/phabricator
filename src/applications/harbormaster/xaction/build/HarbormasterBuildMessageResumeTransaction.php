<?php

final class HarbormasterBuildMessageResumeTransaction
  extends HarbormasterBuildMessageTransaction {

  const TRANSACTIONTYPE = 'message/resume';

  public function getMessageType() {
    return 'resume';
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

}
