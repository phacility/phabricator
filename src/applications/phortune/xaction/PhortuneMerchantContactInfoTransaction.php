<?php

final class PhortuneMerchantContactInfoTransaction
  extends PhortuneMerchantTransactionType {

  const TRANSACTIONTYPE = 'merchant:contactinfo';

  public function generateOldValue($object) {
    return $object->getContactInfo();
  }

  public function applyInternalEffects($object, $value) {
    $object->setContactInfo($value);
  }

  public function getTitle() {
    return pht(
      '%s updated the merchant contact info.',
      $this->renderAuthor());
  }

  public function getTitleForFeed() {
    return pht(
      '%s updated the merchant contact info for %s.',
      $this->renderAuthor(),
      $this->renderObject());
  }

  public function hasChangeDetailView() {
    return true;
  }

  public function getMailDiffSectionHeader() {
    return pht('CHANGES TO MERCHANT CONTACT INFO');
  }

  public function newChangeDetailView() {
    $viewer = $this->getViewer();

    return id(new PhabricatorApplicationTransactionTextDiffDetailView())
      ->setViewer($viewer)
      ->setOldText($this->getOldValue())
      ->setNewText($this->getNewValue());
  }

  public function newRemarkupChanges() {
    $changes = array();

    $changes[] = $this->newRemarkupChange()
      ->setOldValue($this->getOldValue())
      ->setNewValue($this->getNewValue());

    return $changes;
  }

}
