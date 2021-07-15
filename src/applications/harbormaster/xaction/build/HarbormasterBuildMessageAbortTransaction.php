<?php

final class HarbormasterBuildMessageAbortTransaction
  extends HarbormasterBuildMessageTransaction {

  const TRANSACTIONTYPE = 'message/abort';

  public function getMessageType() {
    return 'abort';
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


}
