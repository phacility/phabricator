<?php

final class HeraldRuleDisableTransaction
  extends HeraldRuleTransactionType {

  const TRANSACTIONTYPE = 'herald:disable';

  public function generateOldValue($object) {
    return (bool)$object->getIsDisabled();
  }

  public function generateNewValue($object, $value) {
    return (bool)$value;
  }

  public function applyInternalEffects($object, $value) {
    $object->setIsDisabled((int)$value);
  }

  public function getTitle() {
    if ($this->getNewValue()) {
      return pht(
        '%s disabled this rule.',
        $this->renderAuthor());
    } else {
      return pht(
        '%s enabled this rule.',
        $this->renderAuthor());
    }
  }

}
