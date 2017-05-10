<?php

final class PholioMockDescriptionTransaction
  extends PholioMockTransactionType {

  const TRANSACTIONTYPE = 'description';

  public function generateOldValue($object) {
    return $object->getDescription();
  }

  public function applyInternalEffects($object, $value) {
    $object->setDescription($value);
  }

  public function getTitle() {
    return pht(
      "%s updated the mock's description.",
      $this->renderAuthor());
  }

  public function getTitleForFeed() {
    return pht(
      '%s updated the description for %s.',
      $this->renderAuthor(),
      $this->renderObject());
  }

  public function shouldHide() {
    $old = $this->getOldValue();
    return ($old === null);
  }

  public function hasChangeDetailView() {
    return true;
  }

  public function newChangeDetailView() {
    $viewer = $this->getViewer();

    return id(new PhabricatorApplicationTransactionTextDiffDetailView())
      ->setViewer($viewer)
      ->setOldText($this->getOldValue())
      ->setNewText($this->getNewValue());
  }

}
