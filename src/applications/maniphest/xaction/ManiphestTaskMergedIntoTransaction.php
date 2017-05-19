<?php

final class ManiphestTaskMergedIntoTransaction
  extends ManiphestTaskTransactionType {

  const TRANSACTIONTYPE = 'mergedinto';

  public function generateOldValue($object) {
    return null;
  }

  public function applyInternalEffects($object, $value) {
    $object->setStatus(ManiphestTaskStatus::getDuplicateStatus());
  }

  public function getActionName() {
    return pht('Merged');
  }

  public function getTitle() {
    $new = $this->getNewValue();

    return pht(
      '%s closed this task as a duplicate of %s.',
      $this->renderAuthor(),
      $this->renderHandle($new));
  }

  public function getTitleForFeed() {
    $new = $this->getNewValue();

    return pht(
      '%s merged task %s into %s.',
      $this->renderAuthor(),
      $this->renderObject(),
      $this->renderHandle($new));
  }

  public function getIcon() {
    return 'fa-check';
  }

  public function getColor() {
    return 'indigo';
  }

}
