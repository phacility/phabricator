<?php

final class PhabricatorSlowvoteDescriptionTransaction
  extends PhabricatorSlowvoteTransactionType {

  const TRANSACTIONTYPE = 'vote:description';

  public function generateOldValue($object) {
    return $object->getDescription();
  }

  public function applyInternalEffects($object, $value) {
    $object->setDescription($value);
  }

  public function getTitle() {
    return pht(
      '%s updated the description for this poll.',
      $this->renderAuthor());
  }

  public function getTitleForFeed() {
    $old = $this->getOldValue();

    if ($old === null) {
      return pht(
        '%s set the description of %s.',
        $this->renderAuthor(),
        $this->renderObject());

    } else {
      return pht(
        '%s edited the description of %s.',
        $this->renderAuthor(),
        $this->renderObject());
    }
  }

  public function hasChangeDetailView() {
    return true;
  }

  public function getMailDiffSectionHeader() {
    return pht('CHANGES TO POLL DESCRIPTION');
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
