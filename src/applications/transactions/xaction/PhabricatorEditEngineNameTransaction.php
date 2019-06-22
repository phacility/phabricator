<?php

final class PhabricatorEditEngineNameTransaction
  extends PhabricatorEditEngineTransactionType {

  const TRANSACTIONTYPE = 'editengine.config.name';

  public function generateOldValue($object) {
    return $object->getName();
  }

  public function applyInternalEffects($object, $value) {
    $object->setName($value);
  }

  public function getTitle() {
    if (strlen($this->getOldValue())) {
      return pht(
        '%s renamed this form from %s to %s.',
        $this->renderAuthor(),
        $this->renderOldValue(),
        $this->renderNewValue());
    } else {
      return pht(
        '%s named this form %s.',
        $this->renderAuthor(),
        $this->renderNewValue());
    }
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    foreach ($xactions as $xaction) {
      $new = $xaction->getNewValue();
      if (!strlen($new)) {
        $errors[] = $this->newRequiredError(
          pht('Form name is required.'),
          $xaction);
        continue;
      }
    }

    if (!$errors) {
      if ($this->isEmptyTextTransaction($object->getName(), $xactions)) {
        $errors[] = $this->newRequiredError(
          pht('Forms must have a name.'));
      }
    }

    return $errors;
  }

}
