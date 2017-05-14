<?php

final class PholioMockArchiveController
  extends PholioController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $mock = id(new PholioMockQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$mock) {
      return new Aphront404Response();
    }

    $view_uri = '/M'.$mock->getID();

    if ($request->isFormPost()) {
      if ($mock->isClosed()) {
        $new_status = PholioMock::STATUS_OPEN;
      } else {
        $new_status = PholioMock::STATUS_CLOSED;
      }

      $xactions = array();

      $xactions[] = id(new PholioTransaction())
        ->setTransactionType(PholioMockStatusTransaction::TRANSACTIONTYPE)
        ->setNewValue($new_status);

      id(new PholioMockEditor())
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true)
        ->setContinueOnMissingFields(true)
        ->applyTransactions($mock, $xactions);

      return id(new AphrontRedirectResponse())->setURI($view_uri);
    }

    if ($mock->isClosed()) {
      $title = pht('Open Pholio Mock');
      $body = pht('This mock will become open again.');
      $button = pht('Open Mock');
    } else {
      $title = pht('Close Pholio Mock');
      $body = pht('This mock will be closed.');
      $button = pht('Close Mock');
    }

    return $this->newDialog()
      ->setTitle($title)
      ->appendChild($body)
      ->addCancelButton($view_uri)
      ->addSubmitButton($button);
  }

}
