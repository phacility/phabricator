<?php

final class FundInitiativeDescriptionTransaction
  extends FundInitiativeTransactionType {

  const TRANSACTIONTYPE = 'fund:description';

  public function generateOldValue($object) {
    return $object->getDescription();
  }

  public function applyInternalEffects($object, $value) {
    $object->setDescription($value);
  }

  public function shouldHide() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();
    if (!strlen($old) && !strlen($new)) {
      return true;
    }
    return false;
  }

  public function getTitle() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    if ($old === null) {
      return pht(
        '%s set the initiative description.',
        $this->renderAuthor());
    } else {
      return pht(
        '%s updated the initiative description.',
        $this->renderAuthor());
    }
  }

  public function getTitleForFeed() {
    return pht(
      '%s updated the initiative description for %s.',
      $this->renderAuthor(),
      $this->renderObject());
  }

  public function hasChangeDetailView() {
    return true;
  }

  public function getMailDiffSectionHeader() {
    return pht('CHANGES TO INITIATIVE DESCRIPTION');
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
