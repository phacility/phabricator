<?php

final class PhabricatorCountdownTitleTransaction
  extends PhabricatorCountdownTransactionType {

  const TRANSACTIONTYPE = 'countdown:title';

  public function generateOldValue($object) {
    return $object->getTitle();
  }

  public function applyInternalEffects($object, $value) {
    $object->setTitle($value);
  }

  public function getTitle() {
    return pht(
      '%s updated the title for this countdown from %s to %s.',
      $this->renderAuthor(),
      $this->renderOldValue(),
      $this->renderNewValue());
  }

  public function getTitleForFeed() {
    return pht(
      '%s updated the title for this countdown from %s to %s.',
      $this->renderAuthor(),
      $this->renderOldValue(),
      $this->renderNewValue());
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    if ($this->isEmptyTextTransaction($object->getTitle(), $xactions)) {
      $errors[] = $this->newRequiredError(pht('Countdowns must have a title.'));
    }

    $max_length = $object->getColumnMaximumByteLength('title');
    foreach ($xactions as $xaction) {
      $new_value = $xaction->getNewValue();
      $new_length = strlen($new_value);
      if ($new_length > $max_length) {
        $errors[] = $this->newInvalidError(
          pht(
            'Countdown titles must not be longer than %s character(s).',
            new PhutilNumber($max_length)));
      }
    }

    return $errors;
  }

}
