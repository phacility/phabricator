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

    // If draft isn't the default state but we're creating a new revision
    // and holding it as a draft, put it in draft mode. See PHI206.
    // TODO: This can probably be removed once Draft is the universal default.
    if ($this->isNewObject()) {
      if ($object->isNeedsReview()) {
        $object->setModernRevisionStatus(DifferentialRevisionStatus::DRAFT);
      }
    }
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
