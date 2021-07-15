<?php

final class HarbormasterBuildMessagePauseTransaction
  extends HarbormasterBuildMessageTransaction {

  const TRANSACTIONTYPE = 'message/pause';

  public function getMessageType() {
    return 'pause';
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

}
