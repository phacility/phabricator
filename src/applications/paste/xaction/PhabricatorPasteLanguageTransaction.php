<?php

final class PhabricatorPasteLanguageTransaction
  extends PhabricatorPasteTransactionType {

  const TRANSACTIONTYPE = 'paste.language';

  public function generateOldValue($object) {
    return $object->getLanguage();
  }

  public function applyInternalEffects($object, $value) {
    $object->setLanguage($value);
  }

  private function renderLanguageValue($value) {
    if (!strlen($value)) {
      return $this->renderValue(pht('autodetect'));
    } else {
      return $this->renderValue($value);
    }
  }

  public function getTitle() {
    return pht(
      "%s updated the paste's language from %s to %s.",
      $this->renderAuthor(),
      $this->renderLanguageValue($this->getOldValue()),
      $this->renderLanguageValue($this->getNewValue()));
  }

  public function getTitleForFeed() {
    return pht(
      '%s updated the language for %s from %s to %s.',
      $this->renderAuthor(),
      $this->renderObject(),
      $this->renderLanguageValue($this->getOldValue()),
      $this->renderLanguageValue($this->getNewValue()));
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    foreach ($xactions as $xaction) {
      $new = $xaction->getNewValue();

      if ($new !== null && !strlen($new)) {
        $errors[] = $this->newInvalidError(
          pht('Paste language must be null or a nonempty string.'),
          $xaction);
      }
    }

    return $errors;
  }

}
