<?php

final class FundInitiativeMerchantTransaction
  extends FundInitiativeTransactionType {

  const TRANSACTIONTYPE = 'fund:merchant';

  public function generateOldValue($object) {
    return $object->getMerchantPHID();
  }

  public function applyInternalEffects($object, $value) {
    $object->setMerchantPHID($value);
  }

  public function getTitle() {
    $new = $this->getNewValue();
    $new_merchant = $this->renderHandleList(array($new));

    $old = $this->getOldValue();
    $old_merchant = $this->renderHandleList(array($old));

    if ($old) {
      return pht(
        '%s changed the merchant receiving funds from this '.
        'initiative from %s to %s.',
        $this->renderAuthor(),
        $old_merchant,
        $new_merchant);
    } else {
      return pht(
        '%s set the merchant receiving funds from this '.
        'initiative to %s.',
        $this->renderAuthor(),
        $new_merchant);
    }
  }

  public function getTitleForFeed() {
    $new = $this->getNewValue();
    $new_merchant = $this->renderHandleList(array($new));

    $old = $this->getOldValue();
    $old_merchant = $this->renderHandleList(array($old));

    return pht(
      '%s changed the merchant receiving funds from %s '.
      'from %s to %s.',
      $this->renderAuthor(),
      $this->renderObject(),
      $old_merchant,
      $new_merchant);
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    if ($this->isEmptyTextTransaction($object->getMerchantPHID(), $xactions)) {
      $errors[] = $this->newRequiredError(
        pht('Initiatives must have a payable merchant.'));
    }

    foreach ($xactions as $xaction) {
      $merchant_phid = $xaction->getNewValue();

      // Make sure the actor has permission to edit the merchant they're
      // selecting. You aren't allowed to send payments to an account you
      // do not control.
      $merchants = id(new PhortuneMerchantQuery())
        ->setViewer($this->getActor())
        ->withPHIDs(array($merchant_phid))
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_VIEW,
            PhabricatorPolicyCapability::CAN_EDIT,
          ))
        ->execute();
      if (!$merchants) {
        $errors[] = $this->newInvalidError(
          pht('You must specify a merchant account you control as the '.
              'recipient of funds from this initiative.'));
      }
    }

    return $errors;
  }

  public function getIcon() {
    return 'fa-bank';
  }


}
