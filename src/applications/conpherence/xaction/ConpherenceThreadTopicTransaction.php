<?php

final class ConpherenceThreadTopicTransaction
  extends ConpherenceThreadTransactionType {

  const TRANSACTIONTYPE = 'topic';

  public function generateOldValue($object) {
    return $object->getTopic();
  }

  public function applyInternalEffects($object, $value) {
    $object->setTopic($value);
  }

  public function getTitle() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    if (strlen($new)) {
      return pht(
        '%s set the room topic to %s.',
        $this->renderAuthor(),
        $this->renderNewValue());
    } else {
      return pht(
        '%s removed the room topic.',
        $this->renderAuthor());
    }

  }

  public function getTitleForFeed() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    if (strlen($new)) {
      return pht(
        '%s set the room topic to %s in %s.',
        $this->renderAuthor(),
        $this->renderNewValue(),
        $this->renderObject());
    } else {
      return pht(
        '%s removed the room topic for %s.',
        $this->renderAuthor(),
        $this->renderObject());
    }

  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    $max_length = $object->getColumnMaximumByteLength('topic');
    foreach ($xactions as $xaction) {
      $new_value = $xaction->getNewValue();
      $new_length = strlen($new_value);
      if ($new_length > $max_length) {
        $errors[] = $this->newInvalidError(
          pht('The topic can be no longer than %s characters.',
          new PhutilNumber($max_length)));
      }
    }

    return $errors;
  }

}
