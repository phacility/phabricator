<?php

final class PhabricatorEditEngineDefaultCreateTransaction
  extends PhabricatorEditEngineTransactionType {

  const TRANSACTIONTYPE = 'editengine.config.default.create';

  public function generateOldValue($object) {
    return (int)$object->getIsDefault();
  }

  public function generateNewValue($object, $value) {
    return (int)$value;
  }

  public function applyInternalEffects($object, $value) {
    $object->setIsDefault($value);
  }

  public function getTitle() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    if ($new) {
      return pht(
        '%s added this form to the "Create" menu.',
        $this->renderAuthor());
    } else {
      return pht(
        '%s removed this form from the "Create" menu.',
        $this->renderAuthor());
    }
  }

}
