<?php

final class PhabricatorOwnersPackageAuditingTransaction
  extends PhabricatorOwnersPackageTransactionType {

  const TRANSACTIONTYPE = 'owners.auditing';

  public function generateOldValue($object) {
    return (int)$object->getAuditingEnabled();
  }

  public function generateNewValue($object, $value) {
    return (int)$value;
  }

  public function applyInternalEffects($object, $value) {
    $object->setAuditingEnabled($value);
  }

  public function getTitle() {
    if ($this->getNewValue()) {
      return pht(
        '%s enabled auditing for this package.',
        $this->renderAuthor());
    } else {
      return pht(
        '%s disabled auditing for this package.',
        $this->renderAuthor());
    }
  }

}
