<?php

final class PhabricatorProjectColumnNameTransaction
  extends PhabricatorProjectColumnTransactionType {

  const TRANSACTIONTYPE = 'project:col:name';

  public function generateOldValue($object) {
    return $object->getName();
  }

  public function applyInternalEffects($object, $value) {
    $object->setName($value);
  }

  public function getTitle() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    if (!strlen($old)) {
      return pht(
        '%s named this column %s.',
        $this->renderAuthor(),
        $this->renderNewValue());
    } else if (strlen($new)) {
      return pht(
        '%s renamed this column from %s to %s.',
        $this->renderAuthor(),
        $this->renderOldValue(),
        $this->renderNewValue());
    } else {
      return pht(
        '%s removed the custom name of this column.',
        $this->renderAuthor());
    }
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    if ($this->isEmptyTextTransaction($object->getName(), $xactions)) {
      // The default "Backlog" column is allowed to be unnamed, which
      // means we use the default name.

      // Proxy columns can't have a name, so don't raise an error here.

      if (!$object->isDefaultColumn() && !$object->getProxy()) {
        $errors[] = $this->newRequiredError(
          pht('Columns must have a name.'));
      }
    }

    $max_length = $object->getColumnMaximumByteLength('name');
    foreach ($xactions as $xaction) {
      $new_value = $xaction->getNewValue();
      $new_length = strlen($new_value);
      if ($new_length > $max_length) {
        $errors[] = $this->newInvalidError(
          pht(
            'Column names must not be longer than %s characters.',
            new PhutilNumber($max_length)),
          $xaction);
      }
    }

    return $errors;
  }

}
