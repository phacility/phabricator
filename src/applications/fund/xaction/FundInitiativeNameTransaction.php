<?php

final class FundInitiativeNameTransaction
  extends FundInitiativeTransactionType {

  const TRANSACTIONTYPE = 'fund:name';

  public function generateOldValue($object) {
    return $object->getName();
  }

  public function applyInternalEffects($object, $value) {
    $object->setName($value);
  }

  public function getTitle() {
    $old = $this->getOldValue();
    if (!strlen($old)) {
      return pht(
        '%s created this initiative.',
        $this->renderAuthor());
    } else {
      return pht(
        '%s renamed this initiative from %s to %s.',
        $this->renderAuthor(),
        $this->renderOldValue(),
        $this->renderNewValue());
    }
  }

  public function getTitleForFeed() {
    $old = $this->getOldValue();
    if (!strlen($old)) {
      return pht(
        '%s created initiative %s.',
        $this->renderAuthor(),
        $this->renderObject());
    } else {
      return pht(
        '%s renamed %s from %s to %s.',
        $this->renderAuthor(),
        $this->renderObject(),
        $this->renderOldValue(),
        $this->renderNewValue());
    }
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    if ($this->isEmptyTextTransaction($object->getName(), $xactions)) {
      $errors[] = $this->newRequiredError(
        pht('Initiatives must have a name.'));
    }

    $max_length = $object->getColumnMaximumByteLength('name');
    foreach ($xactions as $xaction) {
      $new_value = $xaction->getNewValue();
      $new_length = strlen($new_value);
      if ($new_length > $max_length) {
        $errors[] = $this->newInvalidError(
          pht('The name can be no longer than %s characters.',
          new PhutilNumber($max_length)));
      }
    }

    return $errors;
  }


}
