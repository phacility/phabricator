<?php

final class PhabricatorCalendarImportDropController
  extends PhabricatorCalendarController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    if (!$request->validateCSRF()) {
      return new Aphront400Response();
    }

    $cancel_uri = $this->getApplicationURI();

    $ids = $request->getStrList('h');
    if ($ids) {
      $files = id(new PhabricatorFileQuery())
        ->setViewer($viewer)
        ->withIDs($ids)
        ->setRaisePolicyExceptions(true)
        ->execute();
    } else {
      $files = array();
    }

    if (!$files) {
      return $this->newDialog()
        ->setTitle(pht('Nothing Uploaded'))
        ->appendParagraph(
          pht(
            'Drag and drop .ics files to upload them and import them into '.
            'Calendar.'))
        ->addCancelButton($cancel_uri, pht('Done'));
    }

    $engine = new PhabricatorCalendarICSFileImportEngine();
    $imports = array();
    foreach ($files as $file) {
      $import = PhabricatorCalendarImport::initializeNewCalendarImport(
        $viewer,
        clone $engine);

      $xactions = array();
      $xactions[] = id(new PhabricatorCalendarImportTransaction())
        ->setTransactionType(
          PhabricatorCalendarImportICSFileTransaction::TRANSACTIONTYPE)
        ->setNewValue($file->getPHID());

      $editor = id(new PhabricatorCalendarImportEditor())
        ->setActor($viewer)
        ->setContinueOnNoEffect(true)
        ->setContinueOnMissingFields(true)
        ->setContentSourceFromRequest($request);

      $editor->applyTransactions($import, $xactions);

      $imports[] = $import;
    }

    $import_phids = mpull($imports, 'getPHID');
    $events = id(new PhabricatorCalendarEventQuery())
      ->setViewer($viewer)
      ->withImportSourcePHIDs($import_phids)
      ->execute();

    if (count($events) == 1) {
      // The user imported exactly one event. This is consistent with dropping
      // a .ics file from an email; just take them to the event.
      $event = head($events);
      $next_uri = $event->getURI();
    } else if (count($imports) > 1) {
      // The user imported multiple different files. Take them to a summary
      // list of generated import activity.
      $source_phids = implode(',', $import_phids);
      $next_uri = '/calendar/import/log/?importSourcePHIDs='.$source_phids;
    } else {
      // The user imported one file, which had zero or more than one event.
      // Take them to the import detail page.
      $import = head($imports);
      $next_uri = $import->getURI();
    }

    return id(new AphrontRedirectResponse())->setURI($next_uri);
  }

}
