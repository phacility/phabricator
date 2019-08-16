<?php

final class PhortuneSubscriptionAutopayTransaction
  extends PhortuneSubscriptionTransactionType {

  const TRANSACTIONTYPE = 'autopay';

  public function generateOldValue($object) {
    return $object->getDefaultPaymentMethodPHID();
  }

  public function applyInternalEffects($object, $value) {
    $object->setDefaultPaymentMethodPHID($value);
  }

  public function getTitle() {
    $old_phid = $this->getOldValue();
    $new_phid = $this->getNewValue();

    if ($old_phid && $new_phid) {
      return pht(
        '%s changed the automatic payment method for this subscription.',
        $this->renderAuthor());
    } else if ($new_phid) {
      return pht(
        '%s configured an automatic payment method for this subscription.',
        $this->renderAuthor());
    } else {
      return pht(
        '%s stopped automatic payments for this subscription.',
        $this->renderAuthor());
    }
  }

  public function shouldTryMFA(
    $object,
    PhabricatorApplicationTransaction $xaction) {
    return true;
  }

}
