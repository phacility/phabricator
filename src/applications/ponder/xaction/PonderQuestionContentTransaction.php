<?php

final class PonderQuestionContentTransaction
  extends PonderQuestionTransactionType {

  const TRANSACTIONTYPE = 'ponder.question:content';

  public function generateOldValue($object) {
    return $object->getContent();
  }

  public function applyInternalEffects($object, $value) {
    $object->setContent($value);
  }

  public function getTitle() {
    return pht(
      '%s updated the question details.',
      $this->renderAuthor());
  }

  public function getTitleForFeed() {
    return pht(
      '%s updated the question details for %s.',
      $this->renderAuthor(),
      $this->renderObject());
  }

  public function hasChangeDetailView() {
    return true;
  }

  public function getMailDiffSectionHeader() {
    return pht('CHANGES TO QUESTION DETAILS');
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
