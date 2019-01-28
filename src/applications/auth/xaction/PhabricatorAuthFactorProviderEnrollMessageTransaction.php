<?php

final class PhabricatorAuthFactorProviderEnrollMessageTransaction
  extends PhabricatorAuthFactorProviderTransactionType {

  const TRANSACTIONTYPE = 'enroll-message';

  public function generateOldValue($object) {
    return $object->getEnrollMessage();
  }

  public function applyInternalEffects($object, $value) {
    $object->setEnrollMessage($value);
  }

  public function getTitle() {
    return pht(
      '%s updated the enroll message.',
      $this->renderAuthor());
  }

  public function hasChangeDetailView() {
    return true;
  }

  public function getMailDiffSectionHeader() {
    return pht('CHANGES TO ENROLL MESSAGE');
  }

  public function newChangeDetailView() {
    $viewer = $this->getViewer();

    return id(new PhabricatorApplicationTransactionTextDiffDetailView())
      ->setViewer($viewer)
      ->setOldText($this->getOldValue())
      ->setNewText($this->getNewValue());
  }

}
