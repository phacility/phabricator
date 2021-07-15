<?php

final class HarbormasterBuildMessageRestartTransaction
  extends HarbormasterBuildMessageTransaction {

  const TRANSACTIONTYPE = 'message/restart';

  public function getMessageType() {
    return 'restart';
  }

  public function getTitle() {
    return pht(
      '%s restarted this build.',
      $this->renderAuthor());
  }

  public function getIcon() {
    return 'fa-backward';
  }

  public function applyInternalEffects($object, $value) {
    $actor = $this->getActor();
    $build = $object;

    $build->restartBuild($actor);
    $build->setBuildStatus(HarbormasterBuildStatus::STATUS_BUILDING);
  }

}
