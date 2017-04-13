<?php

final class PhortuneMerchantInvoiceEmailTransaction
  extends PhortuneMerchantTransactionType {

  const TRANSACTIONTYPE = 'merchant:invoiceemail';

  public function generateOldValue($object) {
    return $object->getInvoiceEmail();
  }

  public function applyInternalEffects($object, $value) {
    $object->setInvoiceEmail($value);
  }

  public function getTitle() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    if (strlen($old) && strlen($new)) {
      return pht(
        '%s updated the invoice email from %s to %s.',
        $this->renderAuthor(),
        $this->renderOldValue(),
        $this->renderNewValue());
    } else if (strlen($old)) {
      return pht(
        '%s removed the invoice email.',
        $this->renderAuthor());
    } else {
      return pht(
      '%s set the invoice email to %s.',
        $this->renderAuthor(),
        $this->renderNewValue());
    }
  }

  public function getTitleForFeed() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    if (strlen($old) && strlen($new)) {
      return pht(
        '%s updated %s invoice email from %s to %s.',
        $this->renderAuthor(),
        $this->renderObject(),
        $this->renderOldValue(),
        $this->renderNewValue());
    } else if (strlen($old)) {
      return pht(
        '%s removed the invoice email for %s.',
        $this->renderAuthor(),
        $this->renderObject());
    } else {
      return pht(
      '%s set the invoice email for %s to %s.',
        $this->renderAuthor(),
        $this->renderObject(),
        $this->renderNewValue());
    }
  }

  public function getIcon() {
    return 'fa-envelope';
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    $max_length = $object->getColumnMaximumByteLength('invoiceEmail');
    foreach ($xactions as $xaction) {
      if (strlen($xaction->getNewValue())) {
        $email = new PhutilEmailAddress($xaction->getNewValue());
        $domain = $email->getDomainName();
        if (!strlen($domain)) {
          $errors[] = $this->newInvalidError(
            pht('Invoice email "%s" must be a valid email.',
            $xaction->getNewValue()));
        }

        $new_value = $xaction->getNewValue();
        $new_length = strlen($new_value);
        if ($new_length > $max_length) {
          $errors[] = $this->newInvalidError(
            pht('The email can be no longer than %s characters.',
            new PhutilNumber($max_length)));
        }
      }
    }

    return $errors;
  }

}
