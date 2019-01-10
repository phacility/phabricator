<?php

final class PhabricatorRepositorySymbolLanguagesTransaction
  extends PhabricatorRepositoryTransactionType {

  const TRANSACTIONTYPE = 'repo:symbol-language';

  public function generateOldValue($object) {
    return $object->getSymbolLanguages();
  }

  public function applyInternalEffects($object, $value) {
    $object->setDetail('symbol-languages', $value);
  }

  public function getTitle() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    if ($old) {
      $display_old = implode(', ', $old);
    } else {
      $display_old = pht('Any');
    }

    if ($new) {
      $display_new = implode(', ', $new);
    } else {
      $display_new = pht('Any');
    }

    return pht(
      '%s changed indexed languages from %s to %s.',
      $this->renderAuthor(),
      $this->renderValue($display_old),
      $this->renderValue($display_new));
  }

}
