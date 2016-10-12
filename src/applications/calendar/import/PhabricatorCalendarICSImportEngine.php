<?php

final class PhabricatorCalendarICSImportEngine
  extends PhabricatorCalendarImportEngine {

  const ENGINETYPE = 'ics';

  public function getImportEngineName() {
    return pht('Import .ics File');
  }

  public function getImportEngineHint() {
    return pht('Import an event in ".ics" (iCalendar) format.');
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

  public function didCreateImport(
    PhabricatorUser $viewer,
    PhabricatorCalendarImport $import) {

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

    $parser = id(new PhutilICSParser());

    $document = $parser->parseICSData($data);

    return $this->importEventDocument($viewer, $import, $document);
  }



}
