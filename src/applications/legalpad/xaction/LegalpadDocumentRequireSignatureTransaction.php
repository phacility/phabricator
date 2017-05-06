<?php

final class LegalpadDocumentRequireSignatureTransaction
  extends LegalpadDocumentTransactionType {

  const TRANSACTIONTYPE = 'legalpad:require-signature';

  public function generateOldValue($object) {
    return $object->getRequireSignature();
  }

  public function applyInternalEffects($object, $value) {
    $object->setRequireSignature($value);
  }

  public function applyExternalEffects($object, $value) {
    if (strlen($value)) {
      $session = new PhabricatorAuthSession();
      queryfx(
        $session->establishConnection('w'),
        'UPDATE %T SET signedLegalpadDocuments = 0',
        $session->getTableName());
    }
  }

  public function getTitle() {
    $new = $this->getNewValue();
    if ($new) {
      return pht(
        '%s set the document to require signatures.',
        $this->renderAuthor());
    } else {
      return pht(
        '%s set the document to not require signatures.',
        $this->renderAuthor());
    }
  }

  public function getTitleForFeed() {
    $new = $this->getNewValue();
    if ($new) {
      return pht(
        '%s set the document %s to require signatures.',
        $this->renderAuthor(),
        $this->renderObject());
    } else {
      return pht(
        '%s set the document %s to not require signatures.',
        $this->renderAuthor(),
        $this->renderObject());
    }
  }

  public function getIcon() {
    return 'fa-pencil-square';
  }

}
