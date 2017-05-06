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

  public function hasChangeDetails() {
    return true;
  }

  public function newChangeDetailView() {
    return $this->renderTextCorpusChangeDetails(
      $this->getViewer(),
      $this->getOldValue(),
      $this->getNewValue());
  }

  public function newRemarkupChanges() {
    $changes = array();

    $changes[] = $this->newRemarkupChange()
      ->setOldValue($this->getOldValue())
      ->setNewValue($this->getNewValue());

    return $changes;
  }

}
