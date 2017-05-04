<?php

final class LegalpadDocumentTextTransaction
  extends LegalpadDocumentTransactionType {

  const TRANSACTIONTYPE = 'text';

  public function generateOldValue($object) {
    $body = $object->getDocumentBody();
    return $body->getText();
  }

  public function applyInternalEffects($object, $value) {
    $body = $object->getDocumentBody();
    $body->setText($value);
    $object->attachDocumentBody($body);
  }

  public function getTitle() {
    return pht(
      '%s updated the document text.',
      $this->renderAuthor());
  }

  public function getTitleForFeed() {
    return pht(
      '%s updated the document text for %s.',
      $this->renderAuthor(),
      $this->renderObject());
  }

  public function hasChangeDetailView() {
    return true;
  }

  public function getMailDiffSectionHeader() {
    return pht('CHANGES TO DOCUMENT TEXT');
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
