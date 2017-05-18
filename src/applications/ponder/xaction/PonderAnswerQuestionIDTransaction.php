<?php

final class PonderAnswerQuestionIDTransaction
  extends PonderAnswerTransactionType {

  const TRANSACTIONTYPE = 'ponder.answer:question-id';

  public function generateOldValue($object) {
    return $object->getQuestionID();
  }

  public function applyInternalEffects($object, $value) {
    $object->setQuestionID($value);
  }

}
