<?php

final class PhabricatorBadgesBadgeQualityTransaction
  extends PhabricatorBadgesBadgeTransactionType {

  const TRANSACTIONTYPE = 'badge.quality';

  public function generateOldValue($object) {
    return $object->getQuality();
  }

  public function applyInternalEffects($object, $value) {
    $object->setQuality($value);
  }

  public function shouldHide() {
    if ($this->isCreateTransaction()) {
      return true;
    }

    return false;
  }

  public function getTitle() {
    $old = $this->getQualityLabel($this->getOldValue());
    $new = $this->getQualityLabel($this->getNewValue());

    return pht(
      '%s updated the quality from %s to %s.',
      $this->renderAuthor(),
      $old,
      $new);
  }

  public function getTitleForFeed() {
    $old = $this->getQualityLabel($this->getOldValue());
    $new = $this->getQualityLabel($this->getNewValue());

    return pht(
      '%s updated %s quality from %s to %s.',
      $this->renderAuthor(),
      $this->renderObject(),
      $new,
      $old);
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    if ($this->isEmptyTextTransaction($object->getQuality(), $xactions)) {
      $errors[] = $this->newRequiredError(
        pht('Badge quality must be set.'));
    }

    $map = PhabricatorBadgesQuality::getQualityMap();
    if (!$map[$object->getQuality()]) {
      $errors[] = $this->newRequiredError(
        pht('Badge quality is not valid.'));
    }

    return $errors;
  }

  private function getQualityLabel($quality) {
    $map = PhabricatorBadgesQuality::getQualityMap();
    $name = $map[$quality]['name'];
    return $this->renderValue($name);
  }

}
