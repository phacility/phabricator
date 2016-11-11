<?php

final class PhabricatorCalendarImportReloadWorker extends PhabricatorWorker {

  const VIA_TRIGGER = 'trigger';
  const VIA_BACKGROUND = 'background';

  protected function doWork() {
    $import = $this->loadImport();
    $viewer = PhabricatorUser::getOmnipotentUser();

    if ($import->getIsDisabled()) {
      return;
    }

    $author = id(new PhabricatorPeopleQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($import->getAuthorPHID()))
      ->needUserSettings(true)
      ->executeOne();

    $import_engine = $import->getEngine();

    $data = $this->getTaskData();
    $import->newLogMessage(
      PhabricatorCalendarImportTriggerLogType::LOGTYPE,
      array(
        'via' => idx($data, 'via', self::VIA_TRIGGER),
      ));

    $import_engine->importEventsFromSource($author, $import, false);
  }

  private function loadImport() {
    $viewer = PhabricatorUser::getOmnipotentUser();

    $data = $this->getTaskData();
    $import_phid = idx($data, 'importPHID');

    $import = id(new PhabricatorCalendarImportQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($import_phid))
      ->executeOne();
    if (!$import) {
      throw new PhabricatorWorkerPermanentFailureException(
        pht(
          'Failed to load import with PHID "%s".',
          $import_phid));
    }

    return $import;
  }

}
