<?php

final class PhabricatorAuthContactNumberStatusTransaction
  extends PhabricatorAuthContactNumberTransactionType {

  const TRANSACTIONTYPE = 'status';

  public function generateOldValue($object) {
    return $object->getStatus();
  }

  public function applyInternalEffects($object, $value) {
    $object->setStatus($value);
  }

  public function getTitle() {
    $new = $this->getNewValue();

    if ($new === PhabricatorAuthContactNumber::STATUS_DISABLED) {
      return pht(
        '%s disabled this contact number.',
        $this->renderAuthor());
    } else {
      return pht(
        '%s enabled this contact number.',
        $this->renderAuthor());
    }
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    $map = PhabricatorAuthContactNumber::getStatusNameMap();

    foreach ($xactions as $xaction) {
      $new_value = $xaction->getNewValue();

      if (!isset($map[$new_value])) {
        $errors[] = $this->newInvalidError(
          pht(
            'Status ("%s") is not a valid contact number status. Valid '.
            'status constants are: %s.',
            $new_value,
            implode(', ', array_keys($map))),
          $xaction);
        continue;
      }

      $mfa_error = $this->newContactNumberMFAError($object, $xaction);
      if ($mfa_error) {
        $errors[] = $mfa_error;
        continue;
      }

      // NOTE: Enabling a contact number may cause us to collide with another
      // active contact number. However, there might also be a transaction in
      // this group that changes the number itself. Since we can't easily
      // predict if we'll collide or not, just let the duplicate key logic
      // handle it when we do.
    }

    return $errors;
  }

}
