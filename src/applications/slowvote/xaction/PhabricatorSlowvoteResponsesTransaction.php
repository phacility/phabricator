<?php

final class PhabricatorSlowvoteResponsesTransaction
  extends PhabricatorSlowvoteTransactionType {

  const TRANSACTIONTYPE = 'vote:responses';

  public function generateOldValue($object) {
    return (string)$object->getResponseVisibility();
  }

  public function generateNewValue($object, $value) {
    return (string)$value;
  }

  public function applyInternalEffects($object, $value) {
    $object->setResponseVisibility($value);
  }

  public function getTitle() {
    $old_name = $this->getOldResponseVisibilityObject()->getName();
    $new_name = $this->getNewResponseVisibilityObject()->getName();

    return pht(
      '%s changed who can see the responses from %s to %s.',
      $this->renderAuthor(),
      $this->renderValue($old_name),
      $this->renderValue($new_name));
  }

  public function getTitleForFeed() {
    $old_name = $this->getOldResponseVisibilityObject()->getName();
    $new_name = $this->getNewResponseVisibilityObject()->getName();

    return pht(
      '%s changed who can see the responses of %s from %s to %s.',
        $this->renderAuthor(),
        $this->renderObject(),
        $this->renderValue($old_name),
        $this->renderValue($new_name));
  }

  private function getOldResponseVisibilityObject() {
    return $this->newResponseVisibilityObject($this->getOldValue());
  }

  private function getNewResponseVisibilityObject() {
    return $this->newResponseVisibilityObject($this->getNewValue());
  }

  private function newResponseVisibilityObject($value) {
    return SlowvotePollResponseVisibility::newResponseVisibilityObject($value);
  }

}
