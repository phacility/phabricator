<?php

final class PhortunePaymentMethodDisableController
  extends PhortuneController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $method_id = $request->getURIData('id');

    $method = id(new PhortunePaymentMethodQuery())
      ->setViewer($viewer)
      ->withIDs(array($method_id))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$method) {
      return new Aphront404Response();
    }

    if ($method->getStatus() == PhortunePaymentMethod::STATUS_DISABLED) {
      return new Aphront400Response();
    }

    $account = $method->getAccount();
    $account_id = $account->getID();
    $account_uri = $this->getApplicationURI("/account/billing/{$account_id}/");

    if ($request->isFormPost()) {

      // TODO: ApplicationTransactions!!!!
      $method
        ->setStatus(PhortunePaymentMethod::STATUS_DISABLED)
        ->save();

      return id(new AphrontRedirectResponse())->setURI($account_uri);
    }

    return $this->newDialog()
      ->setTitle(pht('Remove Payment Method'))
      ->appendParagraph(
        pht(
          'Remove the payment method "%s" from your account?',
          phutil_tag(
            'strong',
            array(),
            $method->getFullDisplayName())))
      ->appendParagraph(
        pht(
          'You will no longer be able to make payments using this payment '.
          'method.'))
      ->addCancelButton($account_uri)
      ->addSubmitButton(pht('Remove Payment Method'));
  }

}
