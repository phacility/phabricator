<?php

final class PhabricatorSlowvoteQuestionTransaction
  extends PhabricatorSlowvoteTransactionType {

  const TRANSACTIONTYPE = 'vote:question';

  public function generateOldValue($object) {
    return $object->getQuestion();
  }

  public function applyInternalEffects($object, $value) {
    $object->setQuestion($value);
  }

  public function getTitle() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    if ($old === null) {
      return pht(
        '%s created this poll.',
        $this->renderAuthor());
    } else {
      return pht(
        '%s changed the poll question from "%s" to "%s".',
        $this->renderAuthor(),
        $old,
        $new);
    }
  }

  public function getTitleForFeed() {
    $old = $this->getOldValue();

    if ($old === null) {
      return pht(
        '%s created %s.',
        $this->renderAuthor(),
        $this->renderObject());

    } else {
      return pht(
        '%s renamed %s.',
        $this->renderAuthor(),
        $this->renderObject());
    }
  }

  public function getIcon() {
    $old = $this->getOldValue();

    if ($old === null) {
      return 'fa-plus';
    } else {
      return 'fa-pencil';
    }
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    if ($this->isEmptyTextTransaction($object->getQuestion(), $xactions)) {
      $errors[] = $this->newRequiredError(pht('Polls must have a question.'));
    }

    return $errors;
  }

}
