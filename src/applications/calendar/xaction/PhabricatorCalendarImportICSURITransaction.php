<?php

final class PhabricatorCalendarImportICSURITransaction
  extends PhabricatorCalendarImportTransactionType {

  const TRANSACTIONTYPE = 'calendar.import.ics.uri';
  const PARAMKEY_URI = 'ics.uri';

  public function generateOldValue($object) {
    return $object->getParameter(self::PARAMKEY_URI);
  }

  public function applyInternalEffects($object, $value) {
    $object->setParameter(self::PARAMKEY_URI, $value);
  }

  public function getTitle() {
    // NOTE: This transaction intentionally does not disclose the actual
    // URI.
    return pht(
      '%s updated the import URI.',
      $this->renderAuthor());
  }

  public function validateTransactions($object, array $xactions) {
    $viewer = $this->getActor();
    $errors = array();

    $ics_type = PhabricatorCalendarICSURIImportEngine::ENGINETYPE;
    $import_type = $object->getEngine()->getImportEngineType();
    if ($import_type != $ics_type) {
      if (!$xactions) {
        return $errors;
      }

      $errors[] = $this->newInvalidError(
        pht(
          'You can not attach an ICS URI to an import type other than '.
          'an ICS URI import (type is "%s").',
          $import_type));

      return $errors;
    }

    $new_value = $object->getParameter(self::PARAMKEY_URI);
    foreach ($xactions as $xaction) {
      $new_value = $xaction->getNewValue();
      if (!strlen($new_value)) {
        continue;
      }

      try {
        PhabricatorEnv::requireValidRemoteURIForFetch(
          $new_value,
          array(
            'http',
            'https',
          ));
      } catch (Exception $ex) {
        $errors[] = $this->newInvalidError(
          $ex->getMessage(),
          $xaction);
      }
    }

    if (!strlen($new_value)) {
      $errors[] = $this->newRequiredError(
        pht('You must select an ".ics" URI to import.'));
    }

    return $errors;
  }
}
