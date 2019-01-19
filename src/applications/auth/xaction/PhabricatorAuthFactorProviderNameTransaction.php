<?php

final class PhabricatorAuthFactorProviderNameTransaction
  extends PhabricatorAuthFactorProviderTransactionType {

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

    if (!strlen($old)) {
      return pht(
        '%s named this provider %s.',
        $this->renderAuthor(),
        $this->renderNewValue());
    } else if (!strlen($new)) {
      return pht(
        '%s removed the name (%s) of this provider.',
        $this->renderAuthor(),
        $this->renderOldValue());
    } else {
      return pht(
        '%s renamed this provider from %s to %s.',
        $this->renderAuthor(),
        $this->renderOldValue(),
        $this->renderNewValue());
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
            'Provider names can not be longer than %s characters.',
            new PhutilNumber($max_length)),
          $xaction);
      }
    }

    return $errors;
  }

  public function getTransactionTypeForConduit($xaction) {
    return 'name';
  }

  public function getFieldValuesForConduit($xaction, $data) {
    return array(
      'old' => $xaction->getOldValue(),
      'new' => $xaction->getNewValue(),
    );
  }

}
