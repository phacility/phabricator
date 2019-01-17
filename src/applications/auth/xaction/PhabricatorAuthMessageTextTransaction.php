<?php

final class PhabricatorAuthMessageTextTransaction
  extends PhabricatorAuthMessageTransactionType {

  const TRANSACTIONTYPE = 'text';

  public function generateOldValue($object) {
    return $object->getMessageText();
  }

  public function applyInternalEffects($object, $value) {
    $object->setMessageText($value);
  }

  public function getTitle() {
    return pht(
      '%s updated the message text.',
      $this->renderAuthor());
  }

  public function hasChangeDetailView() {
    return true;
  }

  public function getMailDiffSectionHeader() {
    return pht('CHANGES TO MESSAGE');
  }

  public function newChangeDetailView() {
    $viewer = $this->getViewer();

    return id(new PhabricatorApplicationTransactionTextDiffDetailView())
      ->setViewer($viewer)
      ->setOldText($this->getOldValue())
      ->setNewText($this->getNewValue());
  }

}
