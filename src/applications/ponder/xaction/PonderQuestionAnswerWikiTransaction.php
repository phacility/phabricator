<?php

final class PonderQuestionAnswerWikiTransaction
  extends PonderQuestionTransactionType {

  const TRANSACTIONTYPE = 'ponder.question:wiki';

  public function generateOldValue($object) {
    return $object->getAnswerWiki();
  }

  public function applyInternalEffects($object, $value) {
    $object->setAnswerWiki($value);
  }

  public function getTitle() {
    return pht(
      '%s updated the answer wiki.',
      $this->renderAuthor());
  }

  public function getTitleForFeed() {
    return pht(
      '%s updated the answer wiki for %s.',
      $this->renderAuthor(),
      $this->renderObject());
  }

  public function hasChangeDetailView() {
    return true;
  }

  public function getMailDiffSectionHeader() {
    return pht('CHANGES TO ANSWER WIKI');
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
