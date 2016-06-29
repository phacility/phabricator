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

  public function getTitle() {
    return pht(
      "%s updated the paste's language.",
      $this->renderAuthor());
  }

  public function getTitleForFeed() {
    return pht(
      '%s updated the language for %s.',
      $this->renderAuthor(),
      $this->renderObject());
  }

}
