<?php

final class PhortuneAccountEmailRotateController
  extends PhortuneAccountController {

  protected function shouldRequireAccountEditCapability() {
    return true;
  }

  protected function handleAccountRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $account = $this->getAccount();

    $address = id(new PhortuneAccountEmailQuery())
      ->setViewer($viewer)
      ->withAccountPHIDs(array($account->getPHID()))
      ->withIDs(array($request->getURIData('addressID')))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$address) {
      return new Aphront404Response();
    }

    $address_uri = $address->getURI();

    if ($request->isFormOrHisecPost()) {
      $xactions = array();

      $xactions[] = $address->getApplicationTransactionTemplate()
        ->setTransactionType(
          PhortuneAccountEmailRotateTransaction::TRANSACTIONTYPE)
        ->setNewValue(true);

      $address->getApplicationTransactionEditor()
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnMissingFields(true)
        ->setContinueOnNoEffect(true)
        ->setCancelURI($address_uri)
        ->applyTransactions($address, $xactions);

      return id(new AphrontRedirectResponse())->setURI($address_uri);
    }

    return $this->newDialog()
      ->setTitle(pht('Rotate Access Key'))
      ->appendParagraph(
        pht(
          'Rotate the access key for email address %s?',
          phutil_tag('strong', array(), $address->getAddress())))
      ->appendParagraph(
        pht(
          'Existing access links which have been sent to this email address '.
          'will stop working.'))
      ->addSubmitButton(pht('Rotate Access Key'))
      ->addCancelButton($address_uri);
  }
}
