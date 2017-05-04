<?php

final class PhabricatorSpacesNamespaceNameTransaction
  extends PhabricatorSpacesNamespaceTransactionType {

  const TRANSACTIONTYPE = 'spaces:name';

  public function generateOldValue($object) {
    return $object->getNamespaceName();
  }

  public function applyInternalEffects($object, $value) {
    $object->setNamespaceName($value);
  }

  public function getTitle() {
    $old = $this->getOldValue();
    if (!strlen($old)) {
      return pht(
        '%s created this space.',
        $this->renderAuthor());
    } else {
      return pht(
        '%s renamed this space from %s to %s.',
        $this->renderAuthor(),
        $this->renderOldValue(),
        $this->renderNewValue());
    }
  }

  public function getTitleForFeed() {
    return pht(
      '%s renamed space %s from %s to %s.',
      $this->renderAuthor(),
      $this->renderObject(),
      $this->renderOldValue(),
      $this->renderNewValue());
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    if ($this->isEmptyTextTransaction($object->getNamespaceName(), $xactions)) {
      $errors[] = $this->newRequiredError(
        pht('Spaces must have a name.'));
    }

    $max_length = $object->getColumnMaximumByteLength('namespaceName');
    foreach ($xactions as $xaction) {
      $new_value = $xaction->getNewValue();
      $new_length = strlen($new_value);
      if ($new_length > $max_length) {
        $errors[] = $this->newInvalidError(
          pht('The name can be no longer than %s characters.',
          new PhutilNumber($max_length)));
      }
    }

    return $errors;
  }

}
