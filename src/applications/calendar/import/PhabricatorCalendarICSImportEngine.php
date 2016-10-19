<?php

final class PhabricatorCalendarICSImportEngine
  extends PhabricatorCalendarImportEngine {

  const ENGINETYPE = 'ics';

  public function getImportEngineName() {
    return pht('Import .ics File');
  }

  public function getImportEngineTypeName() {
    return pht('.ics File');
  }

  public function getImportEngineHint() {
    return pht('Import an event in ".ics" (iCalendar) format.');
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

    $parser = new PhutilICSParser();

    try {
      $document = $parser->parseICSData($data);
    } catch (PhutilICSParserException $ex) {
      // TODO: In theory, it would be nice to store these in a fully abstract
      // form so they can be translated at display time. As-is, we'll store the
      // error messages in whatever language we were using when the parser
      // failure occurred.

      $import->newLogMessage(
        PhabricatorCalendarImportICSLogType::LOGTYPE,
        array(
          'ics.code' => $ex->getParserFailureCode(),
          'ics.message' => $ex->getMessage(),
        ));

      $document = null;
    }

    return $this->importEventDocument($viewer, $import, $document);
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
