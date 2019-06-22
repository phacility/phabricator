<?php

final class PhabricatorEditEngineIsEditTransaction
  extends PhabricatorEditEngineTransactionType {

  const TRANSACTIONTYPE = 'editengine.config.isedit';

  public function generateOldValue($object) {
    return (int)$object->getIsEdit();
  }

  public function generateNewValue($object, $value) {
    return (int)$value;
  }

  public function applyInternalEffects($object, $value) {
    $object->setIsEdit($value);
  }

  public function getTitle() {
    $new = $this->getNewValue();

    if ($new) {
      return pht(
        '%s marked this form as an edit form.',
        $this->renderAuthor());
    } else {
      return pht(
        '%s unmarked this form as an edit form.',
        $this->renderAuthor());
    }
  }

}
