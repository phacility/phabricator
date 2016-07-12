<?php

final class PhabricatorPasteArchiveController
  extends PhabricatorPasteController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $paste = id(new PhabricatorPasteQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$paste) {
      return new Aphront404Response();
    }

    $view_uri = $paste->getURI();

    if ($request->isFormPost()) {
      if ($paste->isArchived()) {
        $new_status = PhabricatorPaste::STATUS_ACTIVE;
      } else {
        $new_status = PhabricatorPaste::STATUS_ARCHIVED;
      }

      $xactions = array();

      $xactions[] = id(new PhabricatorPasteTransaction())
        ->setTransactionType(PhabricatorPasteStatusTransaction::TRANSACTIONTYPE)
        ->setNewValue($new_status);

      id(new PhabricatorPasteEditor())
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true)
        ->setContinueOnMissingFields(true)
        ->applyTransactions($paste, $xactions);

      return id(new AphrontRedirectResponse())->setURI($view_uri);
    }

    if ($paste->isArchived()) {
      $title = pht('Activate Paste');
      $body = pht('This paste will become consumable again.');
      $button = pht('Activate Paste');
    } else {
      $title = pht('Archive Paste');
      $body = pht('This paste will be marked as expired.');
      $button = pht('Archive Paste');
    }

    return $this->newDialog()
      ->setTitle($title)
      ->appendChild($body)
      ->addCancelButton($view_uri)
      ->addSubmitButton($button);
  }

}
