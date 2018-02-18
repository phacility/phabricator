<?php

final class PhabricatorCalendarICSURIImportEngine
  extends PhabricatorCalendarICSImportEngine {

  const ENGINETYPE = 'icsuri';

  public function getImportEngineName() {
    return pht('Import .ics URI');
  }

  public function getImportEngineTypeName() {
    return pht('.ics URI');
  }

  public function getImportEngineHint() {
    return pht('Import or subscribe to a calendar in .ics format by URI.');
  }

  public function supportsTriggers(PhabricatorCalendarImport $import) {
    return true;
  }

  public function appendImportProperties(
    PhabricatorUser $viewer,
    PhabricatorCalendarImport $import,
    PHUIPropertyListView $properties) {

    $uri_key = PhabricatorCalendarImportICSURITransaction::PARAMKEY_URI;
    $uri = $import->getParameter($uri_key);

    // Since the URI may contain a secret hash, don't show it to users who
    // can not edit the import.
    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $import,
      PhabricatorPolicyCapability::CAN_EDIT);
    if (!$can_edit) {
      $uri_display = phutil_tag('em', array(), pht('Restricted'));
    } else if (!PhabricatorEnv::isValidRemoteURIForLink($uri)) {
      $uri_display = $uri;
    } else {
      $uri_display = phutil_tag(
        'a',
        array(
          'href' => $uri,
          'target' => '_blank',
          'rel' => 'noreferrer',
        ),
        $uri);
    }

    $properties->addProperty(pht('Source URI'), $uri_display);
  }

  public function newEditEngineFields(
    PhabricatorEditEngine $engine,
    PhabricatorCalendarImport $import) {
    $fields = array();

    if ($engine->getIsCreate()) {
      $fields[] = id(new PhabricatorTextEditField())
        ->setKey('uri')
        ->setLabel(pht('URI'))
        ->setDescription(pht('URI to import.'))
        ->setTransactionType(
          PhabricatorCalendarImportICSURITransaction::TRANSACTIONTYPE)
        ->setConduitDescription(pht('URI to import.'))
        ->setConduitTypeDescription(pht('New URI.'));
    }

    return $fields;
  }

  public function getDisplayName(PhabricatorCalendarImport $import) {
    return pht('ICS URI');
  }

  public function importEventsFromSource(
    PhabricatorUser $viewer,
    PhabricatorCalendarImport $import,
    $should_queue) {

    $uri_key = PhabricatorCalendarImportICSURITransaction::PARAMKEY_URI;
    $uri = $import->getParameter($uri_key);

    PhabricatorSystemActionEngine::willTakeAction(
      array($viewer->getPHID()),
      new PhabricatorFilesOutboundRequestAction(),
      1);

    $file = PhabricatorFile::newFromFileDownload(
      $uri,
      array(
        'viewPolicy' => PhabricatorPolicies::POLICY_NOONE,
        'authorPHID' => $import->getAuthorPHID(),
        'canCDN' => true,
      ));

    $import->newLogMessage(
      PhabricatorCalendarImportFetchLogType::LOGTYPE,
      array(
        'file.phid' => $file->getPHID(),
      ));

    $data = $file->loadFileData();

    if ($should_queue && $this->shouldQueueDataImport($data)) {
      return $this->queueDataImport($import, $data);
    }

    return $this->importICSData($viewer, $import, $data);
  }

  public function canDisable(
    PhabricatorUser $viewer,
    PhabricatorCalendarImport $import) {
    return true;
  }

}
