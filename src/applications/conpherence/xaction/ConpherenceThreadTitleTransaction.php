<?php

final class ConpherenceThreadTitleTransaction
  extends ConpherenceThreadTransactionType {

  const TRANSACTIONTYPE = 'title';

  public function generateOldValue($object) {
    return $object->getTitle();
  }

  public function applyInternalEffects($object, $value) {
    $object->setTitle($value);
  }

  public function getTitle() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    if (strlen($old) && strlen($new)) {
      return pht(
        '%s renamed this room from %s to %s.',
        $this->renderAuthor(),
        $this->renderOldValue(),
        $this->renderNewValue());
    } else {
      return pht(
      '%s created this room.',
        $this->renderAuthor());
    }
  }

  public function getTitleForFeed() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    if (strlen($old) && strlen($new)) {
      return pht(
        '%s renamed %s from %s to %s.',
        $this->renderAuthor(),
        $this->renderObject(),
        $this->renderOldValue(),
        $this->renderNewValue());
    } else {
      return pht(
      '%s created %s.',
        $this->renderAuthor(),
        $this->renderObject());
    }
  }


  public function validateTransactions($object, array $xactions) {
    $errors = array();

    if ($this->isEmptyTextTransaction($object->getTitle(), $xactions)) {
      $errors[] = $this->newRequiredError(
        pht('Rooms must have a title.'));
    }

    $max_length = $object->getColumnMaximumByteLength('title');
    foreach ($xactions as $xaction) {
      $new_value = $xaction->getNewValue();
      $new_length = strlen($new_value);
      if ($new_length > $max_length) {
        $errors[] = $this->newInvalidError(
          pht('The title can be no longer than %s characters.',
          new PhutilNumber($max_length)));
      }
    }

    return $errors;
  }

}
