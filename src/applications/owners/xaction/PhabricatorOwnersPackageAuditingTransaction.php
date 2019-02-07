<?php

final class PhabricatorOwnersPackageAuditingTransaction
  extends PhabricatorOwnersPackageTransactionType {

  const TRANSACTIONTYPE = 'owners.auditing';

  public function generateOldValue($object) {
    return $object->getAuditingState();
  }

  public function generateNewValue($object, $value) {
    return PhabricatorOwnersAuditRule::getStorageValueFromAPIValue($value);
  }

  public function applyInternalEffects($object, $value) {
    $object->setAuditingState($value);
  }

  public function getTitle() {
    $old_value = $this->getOldValue();
    $new_value = $this->getNewValue();

    $old_rule = PhabricatorOwnersAuditRule::newFromState($old_value);
    $new_rule = PhabricatorOwnersAuditRule::newFromState($new_value);

    return pht(
      '%s changed the audit rule for this package from %s to %s.',
      $this->renderAuthor(),
      $this->renderValue($old_rule->getDisplayName()),
      $this->renderValue($new_rule->getDisplayName()));
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    // See PHI1047. This transaction type accepted some weird stuff. Continue
    // supporting it for now, but move toward sensible consistency.

    $modern_options = PhabricatorOwnersAuditRule::getModernValueMap();
    $deprecated_options = PhabricatorOwnersAuditRule::getDeprecatedValueMap();

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
