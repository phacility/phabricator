<?php

final class PhabricatorAuthContactNumberNumberTransaction
  extends PhabricatorAuthContactNumberTransactionType {

  const TRANSACTIONTYPE = 'number';

  public function generateOldValue($object) {
    return $object->getContactNumber();
  }

  public function generateNewValue($object, $value) {
    $number = new PhabricatorPhoneNumber($value);
    return $number->toE164();
  }

  public function applyInternalEffects($object, $value) {
    $object->setContactNumber($value);
  }

  public function getTitle() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    return pht(
      '%s changed this contact number from %s to %s.',
      $this->renderAuthor(),
      $this->renderOldValue(),
      $this->renderNewValue());
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    $current_value = $object->getContactNumber();
    if ($this->isEmptyTextTransaction($current_value, $xactions)) {
      $errors[] = $this->newRequiredError(
        pht('Contact numbers must have a contact number.'));
      return $errors;
    }

    $max_length = $object->getColumnMaximumByteLength('contactNumber');
    foreach ($xactions as $xaction) {
      $new_value = $xaction->getNewValue();
      $new_length = strlen($new_value);
      if ($new_length > $max_length) {
        $errors[] = $this->newInvalidError(
          pht(
            'Contact numbers can not be longer than %s characters.',
            new PhutilNumber($max_length)),
          $xaction);
        continue;
      }

      try {
        new PhabricatorPhoneNumber($new_value);
      } catch (Exception $ex) {
        $errors[] = $this->newInvalidError(
          pht(
            'Contact number is invalid: %s',
            $ex->getMessage()),
          $xaction);
        continue;
      }

      $new_value = $this->generateNewValue($object, $new_value);

      $unique_key = id(clone $object)
        ->setContactNumber($new_value)
        ->newUniqueKey();

      $other = id(new PhabricatorAuthContactNumberQuery())
        ->setViewer(PhabricatorUser::getOmnipotentUser())
        ->withUniqueKeys(array($unique_key))
        ->executeOne();

      if ($other) {
        if ($other->getID() !== $object->getID()) {
          $errors[] = $this->newInvalidError(
            pht('Contact number is already in use.'),
            $xaction);
          continue;
        }
      }

      $mfa_error = $this->newContactNumberMFAError($object, $xaction);
      if ($mfa_error) {
        $errors[] = $mfa_error;
        continue;
      }
    }

    return $errors;
  }

}
