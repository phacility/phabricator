<?php

final class PhabricatorSlowvoteVotingMethodTransaction
  extends PhabricatorSlowvoteTransactionType {

  const TRANSACTIONTYPE = 'vote:method';

  public function generateOldValue($object) {
    return (string)$object->getMethod();
  }

  public function generateNewValue($object, $value) {
    return (string)$value;
  }

  public function applyInternalEffects($object, $value) {
    $object->setMethod($value);
  }

  public function getTitle() {
    $old_name = $this->getOldVotingMethodObject()->getName();
    $new_name = $this->getNewVotingMethodObject()->getName();

    return pht(
      '%s changed the voting method from %s to %s.',
      $this->renderAuthor(),
      $this->renderValue($old_name),
      $this->renderValue($new_name));
  }

  public function getTitleForFeed() {
    $old_name = $this->getOldVotingMethodObject()->getName();
    $new_name = $this->getNewVotingMethodObject()->getName();

    return pht(
      '%s changed the voting method of %s from %s to %s.',
        $this->renderAuthor(),
        $this->renderObject(),
        $this->renderValue($old_name),
        $this->renderValue($new_name));
  }

  private function getOldVotingMethodObject() {
    return $this->newVotingMethodObject($this->getOldValue());
  }

  private function getNewVotingMethodObject() {
    return $this->newVotingMethodObject($this->getNewValue());
  }

  private function newVotingMethodObject($value) {
    return SlowvotePollVotingMethod::newVotingMethodObject($value);
  }

}
