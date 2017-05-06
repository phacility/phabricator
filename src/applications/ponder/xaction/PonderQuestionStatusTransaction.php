<?php

final class PonderQuestionStatusTransaction
  extends PonderQuestionTransactionType {

  const TRANSACTIONTYPE = 'ponder.question:status';

  public function generateOldValue($object) {
    return $object->getStatus();
  }

  public function applyInternalEffects($object, $value) {
    $object->setStatus($value);
  }

  public function getTitle() {
    $new = $this->getNewValue();
    switch ($new) {
      case PonderQuestionStatus::STATUS_OPEN:
        return pht(
          '%s reopened this question.',
          $this->renderAuthor());
      case PonderQuestionStatus::STATUS_CLOSED_RESOLVED:
        return pht(
          '%s closed this question as resolved.',
          $this->renderAuthor());
      case PonderQuestionStatus::STATUS_CLOSED_OBSOLETE:
        return pht(
          '%s closed this question as obsolete.',
          $this->renderAuthor());
      case PonderQuestionStatus::STATUS_CLOSED_INVALID:
        return pht(
          '%s closed this question as invalid.',
          $this->renderAuthor());
    }
  }

  public function getTitleForFeed() {
    $new = $this->getNewValue();
    switch ($new) {
      case PonderQuestionStatus::STATUS_OPEN:
        return pht(
          '%s reopened %s.',
          $this->renderAuthor(),
          $this->renderObject());
      case PonderQuestionStatus::STATUS_CLOSED_RESOLVED:
        return pht(
          '%s closed %s as resolved.',
          $this->renderAuthor(),
          $this->renderObject());
      case PonderQuestionStatus::STATUS_CLOSED_INVALID:
        return pht(
          '%s closed %s as invalid.',
          $this->renderAuthor(),
          $this->renderObject());
      case PonderQuestionStatus::STATUS_CLOSED_OBSOLETE:
        return pht(
          '%s closed %s as obsolete.',
          $this->renderAuthor(),
          $this->renderObject());
    }
  }

  public function getIcon() {
    $new = $this->getNewValue();
    return PonderQuestionStatus::getQuestionStatusIcon($new);
  }

  public function getColor() {
    $new = $this->getNewValue();
    return PonderQuestionStatus::getQuestionStatusTagColor($new);
  }

}
