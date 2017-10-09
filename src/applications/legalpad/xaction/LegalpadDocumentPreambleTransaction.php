<?php

final class LegalpadDocumentPreambleTransaction
  extends LegalpadDocumentTransactionType {

  // TODO: This is misspelled! See T13005.
  const TRANSACTIONTYPE = 'legalpad:premable';

  public function generateOldValue($object) {
    return $object->getPreamble();
  }

  public function applyInternalEffects($object, $value) {
    $object->setPreamble($value);
  }

  public function getTitle() {
    return pht(
      '%s updated the document preamble.',
      $this->renderAuthor());
  }

  public function getTitleForFeed() {
    return pht(
      '%s updated the document preamble for %s.',
      $this->renderAuthor(),
      $this->renderObject());
  }

  public function hasChangeDetailView() {
    return true;
  }

  public function getMailDiffSectionHeader() {
    return pht('CHANGES TO DOCUMENT PREAMBLE');
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
