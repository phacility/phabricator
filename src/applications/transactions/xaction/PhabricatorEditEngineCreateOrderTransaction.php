<?php

final class PhabricatorEditEngineCreateOrderTransaction
  extends PhabricatorEditEngineTransactionType {

  const TRANSACTIONTYPE = 'editengine.order.create';

  public function generateOldValue($object) {
    return (int)$object->getCreateOrder();
  }

  public function generateNewValue($object, $value) {
    return (int)$value;
  }

  public function applyInternalEffects($object, $value) {
    $object->setCreateOrder($value);
  }

  public function getTitle() {
    return pht(
      '%s changed the order in which this form appears in the "Create" menu.',
      $this->renderAuthor());
  }

}
