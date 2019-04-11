<?php

final class PhabricatorDashboardNameTransaction
  extends PhabricatorDashboardTransactionType {

  const TRANSACTIONTYPE = 'dashboard:name';

  public function generateOldValue($object) {
    return $object->getName();
  }

  public function applyInternalEffects($object, $value) {
    $object->setName($value);
  }

  public function getTitle() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    return pht(
      '%s renamed this dashboard from %s to %s.',
      $this->renderAuthor(),
      $this->renderOldValue(),
      $this->renderNewValue());
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    $max_length = $object->getColumnMaximumByteLength('name');
    foreach ($xactions as $xaction) {
      $new = $xaction->getNewValue();
      if (!strlen($new)) {
        $errors[] = $this->newInvalidError(
          pht('Dashboards must have a name.'),
          $xaction);
        continue;
      }

      if (strlen($new) > $max_length) {
        $errors[] = $this->newInvalidError(
          pht(
            'Dashboard names must not be longer than %s characters.',
            $max_length));
        continue;
      }
    }

    if (!$errors) {
      if ($this->isEmptyTextTransaction($object->getName(), $xactions)) {
        $errors[] = $this->newRequiredError(
          pht('Dashboards must have a name.'));
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
