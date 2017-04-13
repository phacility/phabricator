<?php

final class PhortuneMerchantInvoiceFooterTransaction
  extends PhortuneMerchantTransactionType {

  const TRANSACTIONTYPE = 'merchant:invoicefooter';

  public function generateOldValue($object) {
    return $object->getInvoiceFooter();
  }

  public function applyInternalEffects($object, $value) {
    $object->setInvoiceFooter($value);
  }

  public function getTitle() {
    return pht(
      '%s updated the merchant invoice footer.',
      $this->renderAuthor());
  }

  public function getTitleForFeed() {
    return pht(
      '%s updated the merchant invoice footer for %s.',
      $this->renderAuthor(),
      $this->renderObject());
  }

  public function hasChangeDetailView() {
    return true;
  }

  public function getMailDiffSectionHeader() {
    return pht('CHANGES TO MERCHANT INVOICE FOOTER');
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
