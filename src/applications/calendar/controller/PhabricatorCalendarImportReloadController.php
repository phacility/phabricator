<?php

final class PhabricatorCalendarImportReloadController
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

    if ($request->isFormPost()) {
      $xactions = array();
      $xactions[] = id(new PhabricatorCalendarImportTransaction())
        ->setTransactionType(
          PhabricatorCalendarImportReloadTransaction::TRANSACTIONTYPE)
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
      ->setTitle(pht('Reload Events'))
      ->appendParagraph(
        pht(
          'Reload this source? Events imported from this source will '.
          'be updated.'))
      ->addCancelButton($import_uri)
      ->addSubmitButton(pht('Reload Events'));
  }

}
