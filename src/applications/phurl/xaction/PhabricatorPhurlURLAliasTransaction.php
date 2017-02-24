<?php

final class PhabricatorPhurlURLAliasTransaction
  extends PhabricatorPhurlURLTransactionType {

  const TRANSACTIONTYPE = 'phurl.alias';

  public function generateOldValue($object) {
    return $object->getAlias();
  }

  public function applyInternalEffects($object, $value) {
    $object->setAlias($value);
  }

  public function getTitle() {
    return pht(
      '%s changed the alias from %s to %s.',
      $this->renderAuthor(),
      $this->renderOldValue(),
      $this->renderNewValue());
  }

  public function getTitleForFeed() {
    return pht(
      '%s changed the alias of %s from %s to %s.',
      $this->renderAuthor(),
      $this->renderObject(),
      $this->renderOldValue(),
      $this->renderNewValue());
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    if ($this->isEmptyTextTransaction($object->getAlias(), $xactions)) {
      $errors[] = $this->newRequiredError(
        pht('Phurls must have an alias.'));
    }

    $max_length = $object->getColumnMaximumByteLength('alias');
    foreach ($xactions as $xaction) {
      $new_alias = $xaction->getNewValue();

      // Check length
      $new_length = strlen($new_alias);
      if ($new_length > $max_length) {
        $errors[] = $this->newRequiredError(
          pht('The alias can be no longer than %d characters.', $max_length));
      }

      // Check characters
      if ($xaction->getOldValue() != $xaction->getNewValue()) {
        $debug_alias = new PHUIInvisibleCharacterView($new_alias);
        if (!preg_match('/[a-zA-Z]/', $new_alias)) {
          $errors[] = $this->newRequiredError(
            pht('The alias you provided (%s) must contain at least one '.
              'letter.',
              $debug_alias));
        }
        if (preg_match('/[^a-z0-9]/i', $new_alias)) {
          $errors[] = $this->newRequiredError(
            pht('The alias you provided (%s) may only contain letters and '.
              'numbers.',
              $debug_alias));
        }
      }
    }

    return $errors;
  }

  public function getIcon() {
    return 'fa-compress';
  }

}
