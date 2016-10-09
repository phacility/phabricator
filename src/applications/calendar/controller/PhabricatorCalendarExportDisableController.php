<?php

final class PhabricatorCalendarExportDisableController
  extends PhabricatorCalendarController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $export = id(new PhabricatorCalendarExportQuery())
      ->setViewer($viewer)
      ->withIDs(array($request->getURIData('id')))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$export) {
      return new Aphront404Response();
    }

    $export_uri = $export->getURI();
    $is_disable = !$export->getIsDisabled();

    if ($request->isFormPost()) {
      $xactions = array();
      $xactions[] = id(new PhabricatorCalendarExportTransaction())
        ->setTransactionType(
          PhabricatorCalendarExportDisableTransaction::TRANSACTIONTYPE)
        ->setNewValue($is_disable ? 1 : 0);

      $editor = id(new PhabricatorCalendarExportEditor())
        ->setActor($viewer)
        ->setContinueOnNoEffect(true)
        ->setContinueOnMissingFields(true)
        ->setContentSourceFromRequest($request);

      $editor->applyTransactions($export, $xactions);

      return id(new AphrontRedirectResponse())->setURI($export_uri);
    }

    if ($is_disable) {
      $title = pht('Disable Export');
      $body = pht(
        'Disable this export? The export URI will no longer function.');
      $button = pht('Disable Export');
    } else {
      $title = pht('Enable Export');
      $body = pht(
        'Enable this export? Anyone who knows the export URI will be able '.
        'to export the data.');
      $button = pht('Enable Export');
    }

    return $this->newDialog()
      ->setTitle($title)
      ->appendParagraph($body)
      ->addCancelButton($export_uri)
      ->addSubmitButton($button);
  }

}
