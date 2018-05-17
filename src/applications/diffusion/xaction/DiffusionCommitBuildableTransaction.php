<?php

final class DiffusionCommitBuildableTransaction
  extends DiffusionCommitTransactionType {

  // NOTE: This uses an older constant for compatibility. We should perhaps
  // migrate these at some point.
  const TRANSACTIONTYPE = 'harbormaster:buildable';

  public function generateNewValue($object, $value) {
    return $value;
  }

  public function generateOldValue($object) {
    return null;
  }

  public function getIcon() {
    return $this->newBuildableStatus()->getIcon();
  }

  public function getColor() {
    return $this->newBuildableStatus()->getColor();
  }

  public function getActionName() {
    return $this->newBuildableStatus()->getActionName();
  }

  public function shouldHideForFeed() {
    return !$this->newBuildableStatus()->isFailed();
  }

  public function shouldHideForMail() {
    return !$this->newBuildableStatus()->isFailed();
  }

  public function getTitle() {
    $new = $this->getNewValue();
    $buildable_phid = $this->getBuildablePHID();

    switch ($new) {
      case HarbormasterBuildableStatus::STATUS_PASSED:
        return pht(
          '%s completed building %s.',
          $this->renderAuthor(),
          $this->renderHandle($buildable_phid));
      case HarbormasterBuildableStatus::STATUS_FAILED:
        return pht(
          '%s failed to build %s!',
          $this->renderAuthor(),
          $this->renderHandle($buildable_phid));
    }

    return null;
  }

  public function getTitleForFeed() {
    $new = $this->getNewValue();
    $buildable_phid = $this->getBuildablePHID();

    switch ($new) {
      case HarbormasterBuildableStatus::STATUS_PASSED:
        return pht(
          '%s completed building %s for %s.',
          $this->renderAuthor(),
          $this->renderHandle($buildable_phid),
          $this->renderObject());
      case HarbormasterBuildableStatus::STATUS_FAILED:
        return pht(
          '%s failed to build %s for %s!',
          $this->renderAuthor(),
          $this->renderHandle($buildable_phid),
          $this->renderObject());
    }

    return null;
  }

  private function newBuildableStatus() {
    $new = $this->getNewValue();
    return HarbormasterBuildableStatus::newBuildableStatusObject($new);
  }

  private function getBuildablePHID() {
    return $this->getMetadataValue('harbormaster:buildablePHID');
  }

}
