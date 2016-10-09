<?php

final class PhabricatorCalendarExportModeTransaction
  extends PhabricatorCalendarExportTransactionType {

  const TRANSACTIONTYPE = 'calendar.export.mode';

  public function generateOldValue($object) {
    return $object->getPolicyMode();
  }

  public function applyInternalEffects($object, $value) {
    $object->setPolicyMode($value);
  }

  public function getTitle() {
    $old_value = $this->getOldValue();
    $new_value = $this->getNewValue();

    $old_name = PhabricatorCalendarExport::getPolicyModeName($old_value);
    $new_name = PhabricatorCalendarExport::getPolicyModeName($new_value);

    return pht(
      '%s changed the policy mode for this export from %s to %s.',
      $this->renderAuthor(),
      $this->renderValue($old_name),
      $this->renderValue($new_name));
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    $valid = PhabricatorCalendarExport::getPolicyModes();
    $valid = array_fuse($valid);

    foreach ($xactions as $xaction) {
      $value = $xaction->getNewValue();

      if (isset($valid[$value])) {
        continue;
      }

      $errors[] = $this->newInvalidError(
        pht(
          'Mode "%s" is not a valid policy mode. Valid modes are: %s.',
          $value,
          implode(', ', $valid)),
        $xaction);
    }

    return $errors;
  }

}
