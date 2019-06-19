<?php

final class PhabricatorEditEngineLocksTransaction
  extends PhabricatorEditEngineTransactionType {

  const TRANSACTIONTYPE = 'editengine.config.locks';

  public function generateOldValue($object) {
    return $object->getFieldLocks();
  }

  public function applyInternalEffects($object, $value) {
    $object->setFieldLocks($value);
  }

  public function getTitle() {
    return pht(
      '%s changed locked and hidden fields.',
      $this->renderAuthor());
  }

  public function hasChangeDetailView() {
    return true;
  }

  public function newChangeDetailView() {
    $viewer = $this->getViewer();

    return id(new PhabricatorApplicationTransactionJSONDiffDetailView())
      ->setViewer($viewer)
      ->setOld($this->getOldValue())
      ->setNew($this->getNewValue());
  }

}
