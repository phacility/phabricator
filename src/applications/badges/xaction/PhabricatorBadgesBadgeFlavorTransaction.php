<?php

final class PhabricatorBadgesBadgeFlavorTransaction
  extends PhabricatorBadgesBadgeTransactionType {

  const TRANSACTIONTYPE = 'badge.flavor';

  public function generateOldValue($object) {
    return $object->getFlavor();
  }

  public function applyInternalEffects($object, $value) {
    $object->setFlavor($value);
  }

  public function getTitle() {
    return pht(
      '%s updated the flavor from %s to %s.',
      $this->renderAuthor(),
      $this->renderOldValue(),
      $this->renderNewValue());
  }

  public function getTitleForFeed() {
    return pht(
      '%s updated %s flavor text from %s to %s.',
      $this->renderAuthor(),
      $this->renderObject(),
      $this->renderOldValue(),
      $this->renderNewValue());
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    $max_length = $object->getColumnMaximumByteLength('flavor');
    foreach ($xactions as $xaction) {
      $new_value = $xaction->getNewValue();
      $new_length = strlen($new_value);
      if ($new_length > $max_length) {
        $errors[] = $this->newRequiredError(
          pht('The flavor text can be no longer than %s characters.',
          new PhutilNumber($max_length)));
      }
    }

    return $errors;
  }

}
