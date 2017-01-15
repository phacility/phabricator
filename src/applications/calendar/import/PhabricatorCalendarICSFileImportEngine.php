<?php

final class PhabricatorCalendarICSFileImportEngine
  extends PhabricatorCalendarICSImportEngine {

  const ENGINETYPE = 'icsfile';

  public function getImportEngineName() {
    return pht('Import .ics File');
  }

  public function getImportEngineTypeName() {
    return pht('.ics File');
  }

  public function getImportEngineHint() {
    return pht('Import an event in ".ics" (iCalendar) format.');
  }

  public function supportsTriggers(PhabricatorCalendarImport $import) {
    return false;
  }

  public function appendImportProperties(
    PhabricatorUser $viewer,
    PhabricatorCalendarImport $import,
    PHUIPropertyListView $properties) {

    $phid_key = PhabricatorCalendarImportICSFileTransaction::PARAMKEY_FILE;
    $file_phid = $import->getParameter($phid_key);

    $properties->addProperty(
      pht('Source File'),
      $viewer->renderHandle($file_phid));
  }

  public function newEditEngineFields(
    PhabricatorEditEngine $engine,
    PhabricatorCalendarImport $import) {
    $fields = array();

    if ($engine->getIsCreate()) {
      $fields[] = id(new PhabricatorFileEditField())
        ->setKey('icsFilePHID')
        ->setLabel(pht('ICS File'))
        ->setDescription(pht('ICS file to import.'))
        ->setTransactionType(
          PhabricatorCalendarImportICSFileTransaction::TRANSACTIONTYPE)
        ->setConduitDescription(pht('File PHID to import.'))
        ->setConduitTypeDescription(pht('File PHID.'));
    }

    return $fields;
  }

  public function getDisplayName(PhabricatorCalendarImport $import) {
    $filename_key = PhabricatorCalendarImportICSFileTransaction::PARAMKEY_NAME;
    $filename = $import->getParameter($filename_key);
    if (strlen($filename)) {
      return pht('ICS File "%s"', $filename);
    } else {
      return pht('ICS File');
    }
  }

  public function importEventsFromSource(
    PhabricatorUser $viewer,
    PhabricatorCalendarImport $import,
    $should_queue) {

    $phid_key = PhabricatorCalendarImportICSFileTransaction::PARAMKEY_FILE;
    $file_phid = $import->getParameter($phid_key);

    $file = id(new PhabricatorFileQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($file_phid))
      ->executeOne();
    if (!$file) {
      throw new Exception(
        pht(
          'Unable to load file ("%s") for import.',
          $file_phid));
    }

    $data = $file->loadFileData();

    if ($should_queue && $this->shouldQueueDataImport($data)) {
      return $this->queueDataImport($import, $data);
    }

    return $this->importICSData($viewer, $import, $data);
  }

  public function canDisable(
    PhabricatorUser $viewer,
    PhabricatorCalendarImport $import) {
    return false;
  }

  public function explainCanDisable(
    PhabricatorUser $viewer,
    PhabricatorCalendarImport $import) {
    return pht(
      'You can not disable import of an ICS file because the entire import '.
      'occurs immediately when you upload the file. There is no further '.
      'activity to disable.');
  }


}
