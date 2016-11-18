<?php

final class PhabricatorCalendarExportICSController
  extends PhabricatorCalendarController {

  public function shouldRequireLogin() {
    // Export URIs are available if you know the secret key. We can't do any
    // other kind of authentication because third-party applications like
    // Google Calendar and Calendar.app need to be able to fetch these URIs.
    return false;
  }

  public function handleRequest(AphrontRequest $request) {
    $omnipotent = PhabricatorUser::getOmnipotentUser();

    // NOTE: We're using the omnipotent viewer to fetch the export, but the
    // URI must contain the secret key. Once we load the export we'll figure
    // out who the effective viewer is.
    $export = id(new PhabricatorCalendarExportQuery())
      ->setViewer($omnipotent)
      ->withSecretKeys(array($request->getURIData('secretKey')))
      ->executeOne();
    if (!$export) {
      return new Aphront404Response();
    }

    if ($export->getIsDisabled()) {
      return new Aphront404Response();
    }

    $author = id(new PhabricatorPeopleQuery())
      ->setViewer($omnipotent)
      ->withPHIDs(array($export->getAuthorPHID()))
      ->needUserSettings(true)
      ->executeOne();
    if (!$author) {
      return new Aphront404Response();
    }

    $mode = $export->getPolicyMode();
    switch ($mode) {
      case PhabricatorCalendarExport::MODE_PUBLIC:
        $viewer = new PhabricatorUser();
        break;
      case PhabricatorCalendarExport::MODE_PRIVILEGED:
        $viewer = $author;
        break;
      default:
        throw new Exception(
          pht(
            'This export has an invalid mode ("%s").',
            $mode));
    }

    $engine = id(new PhabricatorCalendarEventSearchEngine())
      ->setViewer($viewer);

    $query_key = $export->getQueryKey();
    $saved = id(new PhabricatorSavedQueryQuery())
      ->setViewer($omnipotent)
      ->withEngineClassNames(array(get_class($engine)))
      ->withQueryKeys(array($query_key))
      ->executeOne();
    if (!$saved) {
      $saved = $engine->buildSavedQueryFromBuiltin($query_key);
    }

    if (!$saved) {
      return new Aphront404Response();
    }

    $saved = clone $saved;

    // Mark this as a query for export, so we get the correct ghost/recurring
    // behaviors. We also want to load all matching events.
    $saved->setParameter('export', true);
    $saved->setParameter('limit', 0xFFFF);

    // Remove any range constraints. We always export all matching events into
    // ICS files.
    $saved->setParameter('rangeStart', null);
    $saved->setParameter('rangeEnd', null);
    $saved->setParameter('upcoming', null);

    // The "month" and "day" display modes imply time ranges.
    $saved->setParameter('display', 'list');

    $query = $engine->buildQueryFromSavedQuery($saved);

    $events = $query
      ->setViewer($viewer)
      ->execute();

    return $this->newICSResponse(
      $viewer,
      $export->getICSFilename(),
      $events);
  }

}
