<?php

final class PhabricatorCalendarImportDisableController
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
    $is_disable = !$import->getIsDisabled();

    if (!$import->getEngine()->canDisable($viewer, $import)) {
      $reason = $import->getEngine()->explainCanDisable($viewer, $import);
      return $this->newDialog()
        ->setTitle(pht('Unable to Disable'))
        ->appendParagraph($reason)
        ->addCancelButton($import_uri);
    }

    if ($request->isFormPost()) {
      $xactions = array();
      $xactions[] = id(new PhabricatorCalendarImportTransaction())
        ->setTransactionType(
          PhabricatorCalendarImportDisableTransaction::TRANSACTIONTYPE)
        ->setNewValue($is_disable ? 1 : 0);

      $editor = id(new PhabricatorCalendarImportEditor())
        ->setActor($viewer)
        ->setContinueOnNoEffect(true)
        ->setContinueOnMissingFields(true)
        ->setContentSourceFromRequest($request);

      $editor->applyTransactions($import, $xactions);

      return id(new AphrontRedirectResponse())->setURI($import_uri);
    }

    if ($is_disable) {
      $title = pht('Disable Import');
      $body = pht(
        'Disable this import? Events from this source will no longer be '.
        'updated.');
      $button = pht('Disable Import');
    } else {
      $title = pht('Enable Import');
      $body = pht(
        'Enable this import? Events from this source will be updated again.');
      $button = pht('Enable Import');
    }

    return $this->newDialog()
      ->setTitle($title)
      ->appendParagraph($body)
      ->addCancelButton($import_uri)
      ->addSubmitButton($button);
  }

}
