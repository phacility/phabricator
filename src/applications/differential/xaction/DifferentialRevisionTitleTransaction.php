<?php

final class DifferentialRevisionTitleTransaction
  extends DifferentialRevisionTransactionType {

  const TRANSACTIONTYPE = 'differential.revision.title';
  const EDITKEY = 'title';

  public function generateOldValue($object) {
    return $object->getTitle();
  }

  public function applyInternalEffects($object, $value) {
    $object->setTitle($value);
  }

  public function getTitle() {
    return pht(
      '%s retitled this revision from %s to %s.',
      $this->renderAuthor(),
      $this->renderOldValue(),
      $this->renderNewValue());
  }

  public function getTitleForFeed() {
    return pht(
      '%s retitled %s from %s to %s.',
      $this->renderAuthor(),
      $this->renderObject(),
      $this->renderOldValue(),
      $this->renderNewValue());
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    if ($this->isEmptyTextTransaction($object->getTitle(), $xactions)) {
      $errors[] = $this->newRequiredError(
        pht('Revisions must have a title.'));
    }

    $max_length = $object->getColumnMaximumByteLength('title');
    foreach ($xactions as $xaction) {
      $new_value = $xaction->getNewValue();
      $new_length = strlen($new_value);
      if ($new_length > $max_length) {
        $errors[] = $this->newInvalidError(
          pht(
            'Revision title is too long: the maximum length of a '.
            'revision title is 255 bytes.'),
          $xaction);
      }
    }

    return $errors;
  }

  public function getTransactionTypeForConduit($xaction) {
    return 'title';
  }

  public function getFieldValuesForConduit($object, $data) {
    return array(
      'old' => $object->getOldValue(),
      'new' => $object->getNewValue(),
    );
  }

}
