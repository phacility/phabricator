<?php

final class PhortuneAccountBillingNameTransaction
  extends PhortuneAccountTransactionType {

  const TRANSACTIONTYPE = 'billing-name';

  public function generateOldValue($object) {
    return $object->getBillingName();
  }

  public function applyInternalEffects($object, $value) {
    $object->setBillingName($value);
  }

  public function getTitle() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    if (strlen($old) && strlen($new)) {
      return pht(
        '%s changed the billing name for this account from %s to %s.',
        $this->renderAuthor(),
        $this->renderOldValue(),
        $this->renderNewValue());
    } else if (strlen($old)) {
      return pht(
        '%s removed the billing name for this account (was %s).',
        $this->renderAuthor(),
        $this->renderOldValue());
    } else {
      return pht(
        '%s set the billing name for this account to %s.',
        $this->renderAuthor(),
        $this->renderNewValue());
    }
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    $max_length = $object->getColumnMaximumByteLength('billingName');
    foreach ($xactions as $xaction) {
      $new_value = $xaction->getNewValue();
      $new_length = strlen($new_value);
      if ($new_length > $max_length) {
        $errors[] = $this->newRequiredError(
          pht('The billing name can be no longer than %s characters.',
          new PhutilNumber($max_length)));
      }
    }

    return $errors;
  }

}
