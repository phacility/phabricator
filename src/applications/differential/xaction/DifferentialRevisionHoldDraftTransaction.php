<?php

final class DifferentialRevisionHoldDraftTransaction
  extends DifferentialRevisionTransactionType {

  const TRANSACTIONTYPE = 'draft';
  const EDITKEY = 'draft';

  public function generateOldValue($object) {
    return (bool)$object->getHoldAsDraft();
  }

  public function generateNewValue($object, $value) {
    return (bool)$value;
  }

  public function applyInternalEffects($object, $value) {
    $object->setHoldAsDraft($value);
  }

  public function getTitle() {
    if ($this->getNewValue()) {
      return pht(
        '%s held this revision as a draft.',
        $this->renderAuthor());
    } else {
      return pht(
        '%s set this revision to automatically submit once builds complete.',
        $this->renderAuthor());
    }
  }

  public function getTitleForFeed() {
    if ($this->getNewValue()) {
      return pht(
        '%s held %s as a draft.',
        $this->renderAuthor(),
        $this->renderObject());
    } else {
      return pht(
        '%s set %s to automatically submit once builds complete.',
        $this->renderAuthor(),
        $this->renderObject());
    }
  }

}
