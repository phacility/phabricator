<?php

final class PhabricatorSlowvoteStatusTransaction
  extends PhabricatorSlowvoteTransactionType {

  const TRANSACTIONTYPE = 'vote:status';

  public function generateOldValue($object) {
    return (string)$object->getStatus();
  }

  public function generateNewValue($object, $value) {
    return (string)$value;
  }

  public function applyInternalEffects($object, $value) {
    $object->setStatus($value);
  }

  public function getTitle() {
    $old_name = $this->getOldStatusObject()->getName();
    $new_name = $this->getNewStatusObject()->getName();

    return pht(
      '%s changed the status of this poll from %s to %s.',
      $this->renderAuthor(),
      $this->renderValue($old_name),
      $this->renderValue($new_name));
  }

  public function getTitleForFeed() {
    $old_name = $this->getOldStatusObject()->getName();
    $new_name = $this->getNewStatusObject()->getName();


    return pht(
      '%s changed the status of %s from %s to %s.',
      $this->renderAuthor(),
      $this->renderObject(),
      $this->renderValue($old_name),
      $this->renderValue($new_name));
  }

  public function getIcon() {
    return $this->getNewStatusObject()->getTransactionIcon();
  }

  private function getOldStatusObject() {
    return $this->newStatusObject($this->getOldValue());
  }

  private function getNewStatusObject() {
    return $this->newStatusObject($this->getNewValue());
  }

  private function newStatusObject($value) {
    return SlowvotePollStatus::newStatusObject($value);
  }

}
