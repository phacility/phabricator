<?php

final class PhabricatorOwnersPackageAuditingTransaction
  extends PhabricatorOwnersPackageTransactionType {

  const TRANSACTIONTYPE = 'owners.auditing';

  public function generateOldValue($object) {
    return (int)$object->getAuditingEnabled();
  }

  public function generateNewValue($object, $value) {
    switch ($value) {
      case PhabricatorOwnersPackage::AUDITING_AUDIT:
        return 1;
      case '1':
        // TODO: Remove, deprecated.
        return 1;
      default:
        return 0;
    }
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

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    // See PHI1047. This transaction type accepted some weird stuff. Continue
    // supporting it for now, but move toward sensible consistency.

    $modern_options = array(
      PhabricatorOwnersPackage::AUDITING_NONE =>
        sprintf('"%s"', PhabricatorOwnersPackage::AUDITING_NONE),
      PhabricatorOwnersPackage::AUDITING_AUDIT =>
        sprintf('"%s"', PhabricatorOwnersPackage::AUDITING_AUDIT),
    );

    $deprecated_options = array(
      '0' => '"0"',
      '1' => '"1"',
      '' => pht('"" (empty string)'),
    );

    foreach ($xactions as $xaction) {
      $new_value = $xaction->getNewValue();

      if (isset($modern_options[$new_value])) {
        continue;
      }

      if (isset($deprecated_options[$new_value])) {
        continue;
      }

      $errors[] = $this->newInvalidError(
        pht(
          'Package auditing value "%s" is not supported. Supported options '.
          'are: %s. Deprecated options are: %s.',
          $new_value,
          implode(', ', $modern_options),
          implode(', ', $deprecated_options)),
        $xaction);
    }

    return $errors;
  }

}
