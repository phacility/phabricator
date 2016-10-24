<?php

final class PhabricatorCalendarImportDeleteController
  extends PhabricatorCalendarController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $import = id(new PhabricatorCalendarImportQuery())
      ->setViewer($viewer)
      ->withIDs(array($request->getURIData('id')))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$import) {
      return new Aphront404Response();
    }

    $import_uri = $import->getURI();

    $engine = $import->getEngine();
    if (!$engine->canDeleteAnyEvents($viewer, $import)) {
      return $this->newDialog()
        ->setTitle(pht('No Imported Events'))
        ->appendParagraph(
          pht(
            'No events from this source currently exist. They may have '.
            'failed to import, have been updated by another source, or '.
            'already have been deleted.'))
        ->addCancelButton($import_uri, pht('Done'));
    }

    if ($request->isFormPost()) {
      $xactions = array();
      $xactions[] = id(new PhabricatorCalendarImportTransaction())
        ->setTransactionType(
          PhabricatorCalendarImportDeleteTransaction::TRANSACTIONTYPE)
        ->setNewValue(true);

      $editor = id(new PhabricatorCalendarImportEditor())
        ->setActor($viewer)
        ->setContinueOnNoEffect(true)
        ->setContinueOnMissingFields(true)
        ->setContentSourceFromRequest($request);

      $editor->applyTransactions($import, $xactions);

      return id(new AphrontRedirectResponse())->setURI($import_uri);
    }

    return $this->newDialog()
      ->setTitle(pht('Delete Imported Events'))
      ->appendParagraph(
        pht(
          'Delete all the events that were imported from this source? '.
          'This action can not be undone.'))
      ->addCancelButton($import_uri)
      ->addSubmitButton(pht('Delete Events'));
  }

}
