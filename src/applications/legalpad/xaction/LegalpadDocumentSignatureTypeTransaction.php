<?php

final class LegalpadDocumentSignatureTypeTransaction
  extends LegalpadDocumentTransactionType {

  const TRANSACTIONTYPE = 'legalpad:signature-type';

  public function generateOldValue($object) {
    return $object->getSignatureType();
  }

  public function applyInternalEffects($object, $value) {
    $object->setSignatureType($value);
  }

  public function getTitle() {
    return pht(
      '%s set the document signature type.',
      $this->renderAuthor());
  }

  public function getTitleForFeed() {
    return pht(
      '%s set the document signature type for %s.',
      $this->renderAuthor(),
      $this->renderObject());
  }

}
