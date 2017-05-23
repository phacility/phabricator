<?php

final class PhrictionDocumentContentTransaction
  extends PhrictionDocumentTransactionType {

  const TRANSACTIONTYPE = 'content';

  public function generateOldValue($object) {
    if ($this->getEditor()->getIsNewObject()) {
      return null;
    }
    return $object->getContent()->getContent();
  }

  public function generateNewValue($object, $value) {
    return $value;
  }

  public function applyInternalEffects($object, $value) {
    $object->setStatus(PhrictionDocumentStatus::STATUS_EXISTS);
  }

  public function applyExternalEffects($object, $value) {
    $this->getEditor()->getNewContent()->setContent($value);
  }

  public function shouldHide() {
    if ($this->getOldValue() === null) {
      return true;
    } else {
      return false;
    }
  }

  public function getActionStrength() {
    return 1.3;
  }

  public function getActionName() {
    return pht('Edited');
  }

  public function getTitle() {
    return pht(
      '%s edited the content of this document.',
      $this->renderAuthor());
  }

  public function getTitleForFeed() {
    return pht(
      '%s edited the content of %s.',
      $this->renderAuthor(),
      $this->renderObject());
  }

  public function hasChangeDetailView() {
    return true;
  }

  public function getMailDiffSectionHeader() {
    return pht('CHANGES TO DOCUMENT CONTENT');
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

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    $content = $object->getContent()->getContent();
    if ($this->isEmptyTextTransaction($content, $xactions)) {
      $errors[] = $this->newRequiredError(
        pht('Documents must have content.'));
    }

    return $errors;
  }

}
