<?php

final class DifferentialRevisionSummaryTransaction
  extends DifferentialRevisionTransactionType {

  const TRANSACTIONTYPE = 'differential.revision.summary';
  const EDITKEY = 'summary';

  public function generateOldValue($object) {
    return $object->getSummary();
  }

  public function applyInternalEffects($object, $value) {
    $object->setSummary($value);
  }

  public function getTitle() {
    return pht(
      '%s edited the summary of this revision.',
      $this->renderAuthor());
  }

  public function getTitleForFeed() {
    return pht(
      '%s updated the summary of %s.',
      $this->renderAuthor(),
      $this->renderObject());
  }

  public function hasChangeDetailView() {
    return true;
  }

  public function getMailDiffSectionHeader() {
    return pht('CHANGES TO REVISION SUMMARY');
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
    return $this->validateCommitMessageCorpusTransactions(
      $object,
      $xactions,
      pht('Summary'));
  }

  public function getTransactionTypeForConduit($xaction) {
    return 'summary';
  }

  public function getFieldValuesForConduit($xaction, $data) {
    return array(
      'old' => $xaction->getOldValue(),
      'new' => $xaction->getNewValue(),
    );
  }

}
