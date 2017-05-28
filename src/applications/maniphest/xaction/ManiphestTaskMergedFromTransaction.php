<?php

final class ManiphestTaskMergedFromTransaction
  extends ManiphestTaskTransactionType {

  const TRANSACTIONTYPE = 'mergedfrom';

  public function generateOldValue($object) {
    return null;
  }

  public function getActionName() {
    return pht('Merged');
  }

  public function getTitle() {
    $new = $this->getNewValue();

    return pht(
      '%s merged %s task(s): %s.',
      $this->renderAuthor(),
      phutil_count($new),
      $this->renderHandleList($new));
  }

  public function getTitleForFeed() {
    $new = $this->getNewValue();

    return pht(
      '%s merged %s task(s) %s into %s.',
      $this->renderAuthor(),
      phutil_count($new),
      $this->renderHandleList($new),
      $this->renderObject());
  }

  public function getIcon() {
    return 'fa-compress';
  }

  public function getColor() {
    return 'orange';
  }

}
