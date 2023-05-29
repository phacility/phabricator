<?php

final class PhabricatorFileAltTextTransaction
  extends PhabricatorFileTransactionType {

  const TRANSACTIONTYPE = 'file:alt';

  public function generateOldValue($object) {
    return $object->getCustomAltText();
  }

  public function generateNewValue($object, $value) {
    $value = phutil_string_cast($value);

    if (!strlen($value)) {
      $value = null;
    }

    return $value;
  }

  public function applyInternalEffects($object, $value) {
    $object->setCustomAltText($value);
  }

  public function getTitle() {
    $old_value = $this->getOldValue();
    $new_value = $this->getNewValue();

    if ($old_value == null || !strlen($old_value)) {
      return pht(
        '%s set the alternate text for this file to %s.',
        $this->renderAuthor(),
        $this->renderNewValue());
    } else if ($new_value === null || !strlen($new_value)) {
      return pht(
        '%s removed the alternate text for this file (was %s).',
        $this->renderAuthor(),
        $this->renderOldValue());
    } else {
      return pht(
        '%s changed the alternate text for this file from %s to %s.',
        $this->renderAuthor(),
        $this->renderOldValue(),
        $this->renderNewValue());
    }
  }

  public function getTitleForFeed() {
    $old_value = $this->getOldValue();
    $new_value = $this->getNewValue();

    if ($old_value === null || !strlen($old_value)) {
      return pht(
        '%s set the alternate text for %s to %s.',
        $this->renderAuthor(),
        $this->renderObject(),
        $this->renderNewValue());
    } else if ($new_value === null || !strlen($new_value)) {
      return pht(
        '%s removed the alternate text for %s (was %s).',
        $this->renderAuthor(),
        $this->renderObject(),
        $this->renderOldValue());
    } else {
      return pht(
        '%s changed the alternate text for %s from %s to %s.',
        $this->renderAuthor(),
        $this->renderObject(),
        $this->renderOldValue(),
        $this->renderNewValue());
    }
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    $max_length = 1024;
    foreach ($xactions as $xaction) {
      $new_value = $xaction->getNewValue();

      $new_length = strlen($new_value);
      if ($new_length > $max_length) {
        $errors[] = $this->newInvalidError(
          pht(
            'File alternate text must not be longer than %s character(s).',
            new PhutilNumber($max_length)));
      }
    }

    return $errors;
  }

}
