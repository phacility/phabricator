<?php

final class PhabricatorCalendarImportICSFileTransaction
  extends PhabricatorCalendarImportTransactionType {

  const TRANSACTIONTYPE = 'calendar.import.ics.file';
  const PARAMKEY_FILE = 'ics.filePHID';
  const PARAMKEY_NAME = 'ics.fileName';

  public function generateOldValue($object) {
    return $object->getParameter(self::PARAMKEY_FILE);
  }

  public function applyInternalEffects($object, $value) {
    $object->setParameter(self::PARAMKEY_FILE, $value);

    $viewer = $this->getActor();
    $file = id(new PhabricatorFileQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($value))
      ->executeOne();
    if ($file) {
      $object->setParameter(self::PARAMKEY_NAME, $file->getName());
    }
  }

  public function getTitle() {
    return pht(
      '%s imported an ICS file.',
      $this->renderAuthor());
  }

  public function validateTransactions($object, array $xactions) {
    $viewer = $this->getActor();
    $errors = array();

    $ics_type = PhabricatorCalendarICSFileImportEngine::ENGINETYPE;
    $import_type = $object->getEngine()->getImportEngineType();
    if ($import_type != $ics_type) {
      if (!$xactions) {
        return $errors;
      }

      $errors[] = $this->newInvalidError(
        pht(
          'You can not attach an ICS file to an import type other than '.
          'an ICS import (type is "%s").',
          $import_type));

      return $errors;
    }

    $new_value = $object->getParameter(self::PARAMKEY_FILE);
    foreach ($xactions as $xaction) {
      $new_value = $xaction->getNewValue();
      if (!strlen($new_value)) {
        continue;
      }

      $file = id(new PhabricatorFileQuery())
        ->setViewer($viewer)
        ->withPHIDs(array($new_value))
        ->executeOne();
      if (!$file) {
        $errors[] = $this->newInvalidError(
          pht(
            'File PHID "%s" is not valid or not visible.',
            $new_value),
          $xaction);
      }
    }

    if (!$new_value) {
      $errors[] = $this->newRequiredError(
        pht('You must select an ".ics" file to import.'));
    }

    return $errors;
  }
}
