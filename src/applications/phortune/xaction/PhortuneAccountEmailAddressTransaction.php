<?php

final class PhortuneAccountEmailAddressTransaction
  extends PhortuneAccountEmailTransactionType {

  const TRANSACTIONTYPE = 'address';

  public function generateOldValue($object) {
    return $object->getAddress();
  }

  public function applyInternalEffects($object, $value) {
    $object->setAddress($value);
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    if ($this->isEmptyTextTransaction($object->getAddress(), $xactions)) {
      $errors[] = $this->newRequiredError(
        pht('You must provide an email address.'));
    }

    $max_length = $object->getColumnMaximumByteLength('address');
    foreach ($xactions as $xaction) {
      $old_value = $xaction->getOldValue();
      $new_value = $xaction->getNewValue();

      $new_length = strlen($new_value);
      if ($new_length > $max_length) {
        $errors[] = $this->newInvalidError(
          pht(
            'The address can be no longer than %s characters.',
            new PhutilNumber($max_length)),
          $xaction);
        continue;
      }

      if (!PhabricatorUserEmail::isValidAddress($new_value)) {
        $errors[] = $this->newInvalidError(
          PhabricatorUserEmail::describeValidAddresses(),
          $xaction);
        continue;
      }

      if ($new_value !== $old_value) {
        if (!$this->isNewObject()) {
          $errors[] = $this->newInvalidError(
            pht(
              'Account email addresses can not be edited once they are '.
              'created. To change the billing address for an account, '.
              'disable the old address and then add a new address.'),
            $xaction);
          continue;
        }
      }

    }

    return $errors;
  }

}
