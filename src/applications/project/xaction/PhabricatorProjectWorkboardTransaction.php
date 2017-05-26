<?php

final class PhabricatorProjectWorkboardTransaction
  extends PhabricatorProjectTransactionType {

  const TRANSACTIONTYPE = 'project:hasworkboard';

  public function generateOldValue($object) {
    return (int)$object->getHasWorkboard();
  }

  public function generateNewValue($object, $value) {
    return (int)$value;
  }

  public function applyInternalEffects($object, $value) {
    $object->setHasWorkboard($value);
  }

  public function getTitle() {
    $new = $this->getNewValue();

    if ($new) {
      return pht(
        '%s enabled the workboard for this project.',
        $this->renderAuthor());
    } else {
      return pht(
        '%s disabled the workboard for this project.',
        $this->renderAuthor());
    }
  }

  public function shouldHide() {
    return true;
  }

}
