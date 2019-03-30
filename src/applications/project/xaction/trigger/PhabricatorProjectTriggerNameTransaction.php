<?php

final class PhabricatorProjectTriggerNameTransaction
  extends PhabricatorProjectTriggerTransactionType {

  const TRANSACTIONTYPE = 'name';

  public function generateOldValue($object) {
    return $object->getName();
  }

  public function applyInternalEffects($object, $value) {
    $object->setName($value);
  }

  public function getTitle() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    if (strlen($old) && strlen($new)) {
      return pht(
        '%s renamed this trigger from %s to %s.',
        $this->renderAuthor(),
        $this->renderOldValue(),
        $this->renderNewValue());
    } else if (strlen($new)) {
      return pht(
        '%s named this trigger %s.',
        $this->renderAuthor(),
        $this->renderNewValue());
    } else {
      return pht(
        '%s stripped the name %s from this trigger.',
        $this->renderAuthor(),
        $this->renderOldValue());
    }
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    $max_length = $object->getColumnMaximumByteLength('name');
    foreach ($xactions as $xaction) {
      $new_value = $xaction->getNewValue();
      $new_length = strlen($new_value);
      if ($new_length > $max_length) {
        $errors[] = $this->newInvalidError(
          pht(
            'Trigger names must not be longer than %s characters.',
            new PhutilNumber($max_length)),
          $xaction);
      }
    }

    return $errors;
  }

}
