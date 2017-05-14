<?php

final class PholioMockNameTransaction
  extends PholioMockTransactionType {

  const TRANSACTIONTYPE = 'name';

  public function generateOldValue($object) {
    return $object->getName();
  }

  public function applyInternalEffects($object, $value) {
    $object->setName($value);
    if ($object->getOriginalName() === null) {
      $object->setOriginalName($this->getNewValue());
    }
  }

  public function getTitle() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    if ($old === null) {
      return pht(
        '%s created %s.',
        $this->renderAuthor(),
        $this->renderValue($new));
    } else {
      return pht(
        '%s renamed this mock from %s to %s.',
        $this->renderAuthor(),
        $this->renderValue($old),
        $this->renderValue($new));
    }
  }

  public function getTitleForFeed() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    if ($old === null) {
      return pht(
        '%s created %s.',
        $this->renderAuthor(),
        $this->renderObject());
    } else {
      return pht(
        '%s renamed %s from %s to %s.',
        $this->renderAuthor(),
        $this->renderObject(),
        $this->renderValue($old),
        $this->renderValue($new));
    }
  }

  public function getColor() {
    $old = $this->getOldValue();

    if ($old === null) {
      return PhabricatorTransactions::COLOR_GREEN;
    }

    return parent::getColor();
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    if ($this->isEmptyTextTransaction($object->getName(), $xactions)) {
      $errors[] = $this->newRequiredError(pht('Mocks must have a name.'));
    }

    $max_length = $object->getColumnMaximumByteLength('name');
    foreach ($xactions as $xaction) {
      $new_value = $xaction->getNewValue();
      $new_length = strlen($new_value);
      if ($new_length > $max_length) {
        $errors[] = $this->newInvalidError(
          pht(
            'Mock names must not be longer than %s character(s).',
            new PhutilNumber($max_length)));
      }
    }

    return $errors;
  }

}
