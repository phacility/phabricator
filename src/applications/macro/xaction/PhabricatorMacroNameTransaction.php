<?php

final class PhabricatorMacroNameTransaction
  extends PhabricatorMacroTransactionType {

  const TRANSACTIONTYPE = 'macro:name';

  public function generateOldValue($object) {
    return $object->getName();
  }

  public function applyInternalEffects($object, $value) {
    $object->setName($value);
  }

  public function getTitle() {
    return pht(
      '%s renamed this macro from %s to %s.',
      $this->renderAuthor(),
      $this->renderOldValue(),
      $this->renderNewValue());
  }

  public function getTitleForFeed() {
    return pht(
      '%s renamed %s macro %s to %s.',
      $this->renderAuthor(),
      $this->renderObject(),
      $this->renderOldValue(),
      $this->renderNewValue());
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();
    $viewer = $this->getActor();

    if ($this->isEmptyTextTransaction($object->getName(), $xactions)) {
      $errors[] = $this->newRequiredError(
        pht('Macros must have a name.'));
    }

    $max_length = $object->getColumnMaximumByteLength('name');
    foreach ($xactions as $xaction) {
      $old_value = $this->generateOldValue($object);
      $new_value = $xaction->getNewValue();

      $new_length = strlen($new_value);
      if ($new_length > $max_length) {
        $errors[] = $this->newInvalidError(
          pht('The name can be no longer than %s characters.',
          new PhutilNumber($max_length)));
      }

      if (!preg_match('/^[a-z0-9:_-]{3,}\z/', $new_value)) {
      $errors[] = $this->newInvalidError(
        pht('Macro name "%s" be at least three characters long and contain '.
            'only lowercase letters, digits, hyphens, colons and '.
            'underscores.',
            $new_value));
      }

      // Check name is unique when updating / creating
      if ($old_value != $new_value) {
        $macro = id(new PhabricatorMacroQuery())
          ->setViewer($viewer)
          ->withNames(array($new_value))
          ->executeOne();

        if ($macro) {
        $errors[] = $this->newInvalidError(
          pht('Macro "%s" already exists.', $new_value));
        }
      }

    }

    return $errors;
  }

}
