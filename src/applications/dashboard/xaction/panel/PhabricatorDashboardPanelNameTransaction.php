<?php

final class PhabricatorDashboardPanelNameTransaction
  extends PhabricatorDashboardPanelTransactionType {

  const TRANSACTIONTYPE = 'dashpanel:name';

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
      '%s renamed this panel from %s to %s.',
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
          pht('Panels must have a title.'),
          $xaction);
        continue;
      }

      if (strlen($new) > $max_length) {
        $errors[] = $this->newInvalidError(
          pht(
            'Panel names must not be longer than %s characters.',
            $max_length));
        continue;
      }
    }

    if (!$errors) {
      if ($this->isEmptyTextTransaction($object->getName(), $xactions)) {
        $errors[] = $this->newRequiredError(
          pht('Panels must have a title.'));
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
