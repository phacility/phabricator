<?php

final class PonderAnswerStatusTransaction
  extends PonderAnswerTransactionType {

  const TRANSACTIONTYPE = 'ponder.answer:status';

  public function generateOldValue($object) {
    return $object->getStatus();
  }

  public function applyInternalEffects($object, $value) {
    $object->setStatus($value);
  }

  public function getTitle() {
    $new = $this->getNewValue();
    if ($new == PonderAnswerStatus::ANSWER_STATUS_VISIBLE) {
      return pht(
        '%s marked this answer as visible.',
        $this->renderAuthor());
    } else if ($new == PonderAnswerStatus::ANSWER_STATUS_HIDDEN) {
      return pht(
        '%s marked this answer as hidden.',
        $this->renderAuthor());
    }
  }

  public function getTitleForFeed() {
    $new = $this->getNewValue();
    if ($new == PonderAnswerStatus::ANSWER_STATUS_VISIBLE) {
      return pht(
        '%s marked %s as visible.',
        $this->renderAuthor(),
        $this->renderObject());
    } else if ($new == PonderAnswerStatus::ANSWER_STATUS_HIDDEN) {
      return pht(
        '%s marked %s as hidden.',
        $this->renderAuthor(),
        $this->renderObject());
    }
  }

  public function getIcon() {
    $new = $this->getNewValue();
    if ($new == PonderAnswerStatus::ANSWER_STATUS_VISIBLE) {
      return 'fa-ban';
    } else if ($new == PonderAnswerStatus::ANSWER_STATUS_HIDDEN) {
      return 'fa-check';
    }
  }

  public function getColor() {
    $new = $this->getNewValue();
    if ($new == PonderAnswerStatus::ANSWER_STATUS_VISIBLE) {
      return 'green';
    } else if ($new == PonderAnswerStatus::ANSWER_STATUS_HIDDEN) {
      return 'indigo';
    }
  }

}
