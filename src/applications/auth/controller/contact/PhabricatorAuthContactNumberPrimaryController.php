<?php

final class PhabricatorAuthContactNumberPrimaryController
  extends PhabricatorAuthContactNumberController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $number = id(new PhabricatorAuthContactNumberQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$number) {
      return new Aphront404Response();
    }

    $id = $number->getID();
    $cancel_uri = $number->getURI();

    if ($number->isDisabled()) {
      return $this->newDialog()
        ->setTitle(pht('Number Disabled'))
        ->appendParagraph(
          pht(
            'You can not make a disabled number your primary contact number.'))
        ->addCancelButton($cancel_uri);
    }

    if ($number->getIsPrimary()) {
      return $this->newDialog()
        ->setTitle(pht('Number Already Primary'))
        ->appendParagraph(
          pht(
            'This contact number is already your primary contact number.'))
        ->addCancelButton($cancel_uri);
    }

    if ($request->isFormOrHisecPost()) {
      $xactions = array();

      $xactions[] = id(new PhabricatorAuthContactNumberTransaction())
        ->setTransactionType(
          PhabricatorAuthContactNumberPrimaryTransaction::TRANSACTIONTYPE)
        ->setNewValue(true);

      $editor = id(new PhabricatorAuthContactNumberEditor())
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true)
        ->setContinueOnMissingFields(true)
        ->setCancelURI($cancel_uri);

      try {
        $editor->applyTransactions($number, $xactions);
      } catch (PhabricatorApplicationTransactionValidationException $ex) {
        // This happens when you try to make a number into your primary
        // number, but you have contact number MFA on your account.
        return $this->newDialog()
          ->setTitle(pht('Unable to Make Primary'))
          ->setValidationException($ex)
          ->addCancelButton($cancel_uri);
      }

      return id(new AphrontRedirectResponse())->setURI($cancel_uri);
    }

    $number_display = phutil_tag(
      'strong',
      array(),
      $number->getDisplayName());

    return $this->newDialog()
      ->setTitle(pht('Set Primary Contact Number'))
      ->appendParagraph(
        pht(
          'Designate %s as your primary contact number?',
          $number_display))
      ->addSubmitButton(pht('Make Primary'))
      ->addCancelButton($cancel_uri);
  }

}
