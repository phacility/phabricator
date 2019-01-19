<?php

final class PhortuneAccountBillingAddressTransaction
  extends PhortuneAccountTransactionType {

  const TRANSACTIONTYPE = 'billing-address';

  public function generateOldValue($object) {
    return $object->getBillingAddress();
  }

  public function applyInternalEffects($object, $value) {
    $object->setBillingAddress($value);
  }

  public function getTitle() {
    return pht(
      '%s updated the account billing address.',
      $this->renderAuthor());
  }

  public function hasChangeDetailView() {
    return true;
  }

  public function getMailDiffSectionHeader() {
    return pht('CHANGES TO BILLING ADDRESS');
  }

  public function newChangeDetailView() {
    $viewer = $this->getViewer();

    return id(new PhabricatorApplicationTransactionTextDiffDetailView())
      ->setViewer($viewer)
      ->setOldText($this->getOldValue())
      ->setNewText($this->getNewValue());
  }

}
