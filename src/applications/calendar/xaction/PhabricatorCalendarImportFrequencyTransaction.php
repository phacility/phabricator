<?php

final class PhabricatorCalendarImportFrequencyTransaction
  extends PhabricatorCalendarImportTransactionType {

  const TRANSACTIONTYPE = 'calendar.import.frequency';

  public function generateOldValue($object) {
    return $object->getTriggerFrequency();
  }

  public function applyInternalEffects($object, $value) {
    $object->setTriggerFrequency($value);
  }

  public function getTitle() {
    return pht(
      '%s changed the automatic update frequency for this import.',
      $this->renderAuthor());
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    $frequency_map = PhabricatorCalendarImport::getTriggerFrequencyMap();
    $valid = array_keys($frequency_map);
    $valid = array_fuse($valid);

    foreach ($xactions as $xaction) {
      $value = $xaction->getNewValue();

      if (!isset($valid[$value])) {
        $errors[] = $this->newInvalidError(
          pht(
            'Import frequency "%s" is not valid. Valid frequences are: %s.',
            $value,
            implode(', ', $valid)),
          $xaction);
      }
    }

    return $errors;
  }

}
