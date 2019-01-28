<?php

final class PhabricatorAuthFactorProviderMessageController
  extends PhabricatorAuthFactorProviderController {

  public function handleRequest(AphrontRequest $request) {
    $this->requireApplicationCapability(
      AuthManageProvidersCapability::CAPABILITY);

    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $provider = id(new PhabricatorAuthFactorProviderQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$provider) {
      return new Aphront404Response();
    }

    $cancel_uri = $provider->getURI();
    $enroll_key =
      PhabricatorAuthFactorProviderEnrollMessageTransaction::TRANSACTIONTYPE;

    $message = $provider->getEnrollMessage();

    if ($request->isFormOrHisecPost()) {
      $message = $request->getStr('message');

      $xactions = array();

      $xactions[] = id(new PhabricatorAuthFactorProviderTransaction())
        ->setTransactionType($enroll_key)
        ->setNewValue($message);

      $editor = id(new PhabricatorAuthFactorProviderEditor())
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true)
        ->setContinueOnMissingFields(true)
        ->setCancelURI($cancel_uri);

      $editor->applyTransactions($provider, $xactions);

      return id(new AphrontRedirectResponse())->setURI($cancel_uri);
    }

    $default_message = $provider->getEnrollDescription($viewer);
    $default_message = new PHUIRemarkupView($viewer, $default_message);

    $form = id(new AphrontFormView())
      ->setViewer($viewer)
      ->appendRemarkupInstructions(
        pht(
          'When users add a factor for this provider, they are given this '.
          'enrollment guidance by default:'))
      ->appendControl(
        id(new AphrontFormMarkupControl())
          ->setLabel(pht('Default Message'))
          ->setValue($default_message))
      ->appendRemarkupInstructions(
        pht(
          'You may optionally customize the enrollment message users are '.
          'presented with by providing a replacement message below:'))
      ->appendControl(
        id(new PhabricatorRemarkupControl())
          ->setLabel(pht('Custom Message'))
          ->setName('message')
          ->setValue($message));

    return $this->newDialog()
      ->setTitle(pht('Change Enroll Message'))
      ->setWidth(AphrontDialogView::WIDTH_FORM)
      ->appendForm($form)
      ->addCancelButton($cancel_uri)
      ->addSubmitButton(pht('Save'));
  }

}
