<?php

final class PhabricatorSpacesNamespaceDefaultTransaction
  extends PhabricatorSpacesNamespaceTransactionType {

  const TRANSACTIONTYPE = 'spaces:default';

  public function generateOldValue($object) {
    return $object->getIsDefaultNamespace();
  }

  public function applyInternalEffects($object, $value) {
    $object->setIsDefaultNamespace($value);
  }

  public function getTitle() {
    return pht(
      '%s made this the default space.',
      $this->renderAuthor());
  }

  public function getTitleForFeed() {
    return pht(
      '%s made space %s the default space.',
      $this->renderAuthor(),
      $this->renderObject());

  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    if (!$this->isNewObject()) {
      foreach ($xactions as $xaction) {
        $errors[] = $this->newInvalidError(
          pht('Only the first space created can be the default space, and '.
              'it must remain the default space evermore.'));
      }
    }

    return $errors;
  }

}
