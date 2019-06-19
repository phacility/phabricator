<?php

final class PhabricatorEditEnginePreambleTransaction
  extends PhabricatorEditEngineTransactionType {

  const TRANSACTIONTYPE = 'editengine.config.preamble';

  public function generateOldValue($object) {
    return $object->getPreamble();
  }

  public function applyInternalEffects($object, $value) {
    $object->setPreamble($value);
  }

  public function getTitle() {
    return pht(
      '%s updated the preamble for this form.',
      $this->renderAuthor());
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

  public function newRemarkupChanges() {
    $changes = array();

    $changes[] = $this->newRemarkupChange()
      ->setOldValue($this->getOldValue())
      ->setNewValue($this->getNewValue());

    return $changes;
  }

}
