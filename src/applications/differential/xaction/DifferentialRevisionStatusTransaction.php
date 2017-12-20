<?php

final class DifferentialRevisionStatusTransaction
  extends DifferentialRevisionTransactionType {

  const TRANSACTIONTYPE = 'differential.revision.status';

  public function generateOldValue($object) {
    return $object->getModernRevisionStatus();
  }

  public function applyInternalEffects($object, $value) {
    $object->setModernRevisionStatus($value);
  }

  public function getTitle() {
    $status = $this->newStatusObject();

    if ($status->isAccepted()) {
      return pht('This revision is now accepted and ready to land.');
    }

    if ($status->isNeedsRevision()) {
      return pht('This revision now requires changes to proceed.');
    }

    if ($status->isNeedsReview()) {
      return pht('This revision now requires review to proceed.');
    }

    return null;
  }

  public function getTitleForFeed() {
    $status = $this->newStatusObject();

    if ($status->isAccepted()) {
      return pht(
        '%s is now accepted and ready to land.',
        $this->renderObject());
    }

    if ($status->isNeedsRevision()) {
      return pht(
        '%s now requires changes to proceed.',
        $this->renderObject());
    }

    if ($status->isNeedsReview()) {
      return pht(
        '%s now requires review to proceed.',
        $this->renderObject());
    }

    return null;
  }

  public function getIcon() {
    $status = $this->newStatusObject();
    return $status->getTimelineIcon();
  }

  public function getColor() {
    $status = $this->newStatusObject();
    return $status->getTimelineColor();
  }

  private function newStatusObject() {
    $new = $this->getNewValue();
    return DifferentialRevisionStatus::newForStatus($new);
  }

  public function getTransactionTypeForConduit($xaction) {
    return 'status';
  }

  public function getFieldValuesForConduit($xaction, $data) {
    return array(
      'old' => $xaction->getOldValue(),
      'new' => $xaction->getNewValue(),
    );
  }

}
