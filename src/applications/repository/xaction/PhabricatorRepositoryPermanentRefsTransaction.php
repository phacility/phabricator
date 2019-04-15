<?php

final class PhabricatorRepositoryPermanentRefsTransaction
  extends PhabricatorRepositoryTransactionType {

  const TRANSACTIONTYPE = 'repo:autoclose-only';

  public function generateOldValue($object) {
    return $object->getPermanentRefRules();
  }

  public function applyInternalEffects($object, $value) {
    $object->setPermanentRefRules($value);
  }

  public function getTitle() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    if (!$new) {
      return pht(
        '%s marked all branches in this repository as permanent.',
        $this->renderAuthor());
    } else if (!$old) {
      return pht(
        '%s set the permanent refs for this repository to: %s.',
        $this->renderAuthor(),
        $this->renderValue(implode(', ', $new)));
    } else {
      return pht(
        '%s changed permanent refs for this repository from %s to %s.',
        $this->renderAuthor(),
        $this->renderValue(implode(', ', $old)),
        $this->renderValue(implode(', ', $new)));
    }
  }

  public function validateTransactions($object, array $xactions) {
    return $this->validateRefList($object, $xactions);
  }

}
