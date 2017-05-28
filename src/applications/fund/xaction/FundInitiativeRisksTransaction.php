<?php

final class FundInitiativeRisksTransaction
  extends FundInitiativeTransactionType {

  const TRANSACTIONTYPE = 'fund:risks';

  public function generateOldValue($object) {
    return $object->getRisks();
  }

  public function applyInternalEffects($object, $value) {
    $object->setRisks($value);
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
        '%s set the initiative risks/challenges.',
        $this->renderAuthor());
    } else {
      return pht(
        '%s updated the initiative risks/challenges.',
        $this->renderAuthor());
    }

  }

  public function getTitleForFeed() {
    return pht(
      '%s updated the initiative risks/challenges for %s.',
      $this->renderAuthor(),
      $this->renderObject());
  }

  public function hasChangeDetailView() {
    return true;
  }

  public function getMailDiffSectionHeader() {
    return pht('CHANGES TO INITIATIVE RISKS/CHALLENGES');
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

  public function getIcon() {
    return 'fa-ambulance';
  }


}
