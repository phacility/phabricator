<?php

final class PhabricatorEditEngineDisableTransaction
  extends PhabricatorEditEngineTransactionType {

  const TRANSACTIONTYPE = 'editengine.config.disable';

  public function generateOldValue($object) {
    return (int)$object->getIsDisabled();
  }

  public function generateNewValue($object, $value) {
    return (int)$value;
  }

  public function applyInternalEffects($object, $value) {
    $object->setIsDisabled($value);
  }

  public function getTitle() {
    $new = $this->getNewValue();
    if ($new) {
      return pht(
        '%s disabled this form.',
        $this->renderAuthor());
    } else {
      return pht(
        '%s enabled this form.',
        $this->renderAuthor());
    }
  }

  public function getColor() {
    $new = $this->getNewValue();
    if ($new) {
      return 'indigo';
    } else {
      return 'green';
    }
  }

  public function getIcon() {
    $new = $this->getNewValue();
    if ($new) {
      return 'fa-ban';
    } else {
      return 'fa-check';
    }
  }

}
