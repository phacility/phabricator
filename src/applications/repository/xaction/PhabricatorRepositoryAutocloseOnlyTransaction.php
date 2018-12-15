<?php

final class PhabricatorRepositoryAutocloseOnlyTransaction
  extends PhabricatorRepositoryTransactionType {

  const TRANSACTIONTYPE = 'repo:autoclose-only';

  public function generateOldValue($object) {
    return $object->getAutocloseOnlyRules();
  }

  public function applyInternalEffects($object, $value) {
    $object->setAutocloseOnlyRules($value);
  }

  public function getTitle() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    if (!$new) {
      return pht(
        '%s set this repository to autoclose on all branches.',
        $this->renderAuthor());
    } else if (!$old) {
      return pht(
        '%s set this repository to autoclose on branches: %s.',
        $this->renderAuthor(),
        $this->renderValue(implode(', ', $new)));
    } else {
      return pht(
        '%s changed autoclose branches from %s to %s.',
        $this->renderAuthor(),
        $this->renderValue(implode(', ', $old)),
        $this->renderValue(implode(', ', $new)));
    }
  }

  public function validateTransactions($object, array $xactions) {
    return $this->validateRefList($object, $xactions);
  }

}
