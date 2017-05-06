<?php

final class PonderAnswerContentTransaction
  extends PonderAnswerTransactionType {

  const TRANSACTIONTYPE = 'ponder.answer:content';

  public function generateOldValue($object) {
    return $object->getContent();
  }

  public function applyInternalEffects($object, $value) {
    $object->setContent($value);
  }

  public function getTitle() {
    return pht(
      '%s updated the answer details.',
      $this->renderAuthor());
  }

  public function getTitleForFeed() {
    return pht(
      '%s updated the answer details for %s.',
      $this->renderAuthor(),
      $this->renderObject());
  }

  public function hasChangeDetailView() {
    return true;
  }

  public function getMailDiffSectionHeader() {
    return pht('CHANGES TO ANSWER DETAILS');
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
