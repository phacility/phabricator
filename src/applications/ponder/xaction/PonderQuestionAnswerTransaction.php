<?php

final class PonderQuestionAnswerTransaction
  extends PonderQuestionTransactionType {

  const TRANSACTIONTYPE = 'ponder.question:answer';

  public function generateOldValue($object) {
    return $object->getAnswers();
  }

  public function applyInternalEffects($object, $value) {
    $count = $object->getAnswerCount();
    $count++;
    $object->setAnswerCount($count);
  }

  public function getTitle() {
    return pht(
      '%s added an answer.',
      $this->renderAuthor());
  }

  public function getTitleForFeed() {
    return pht(
      '%s added an answer to %s.',
      $this->renderAuthor(),
      $this->renderObject());
  }

  public function getIcon() {
    return 'fa-plus';
  }

}
