<?php

final class PhabricatorEditEngineEditOrderTransaction
  extends PhabricatorEditEngineTransactionType {

  const TRANSACTIONTYPE = 'editengine.order.edit';

  public function generateOldValue($object) {
    return (int)$object->getEditOrder();
  }

  public function generateNewValue($object, $value) {
    return (int)$value;
  }

  public function applyInternalEffects($object, $value) {
    $object->setEditOrder($value);
  }

  public function getTitle() {
    return pht(
      '%s changed the order in which this form appears in the "Edit" menu.',
      $this->renderAuthor());
  }

}
