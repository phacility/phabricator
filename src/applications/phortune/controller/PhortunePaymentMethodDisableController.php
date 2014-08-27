<?php

final class PhortunePaymentMethodDisableController
  extends PhortuneController {

  private $methodID;

  public function willProcessRequest(array $data) {
    $this->methodID = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $method = id(new PhortunePaymentMethodQuery())
      ->setViewer($viewer)
      ->withIDs(array($this->methodID))
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
    $account_uri = $this->getApplicationURI($account->getID().'/');

    if ($request->isFormPost()) {

      // TODO: ApplicationTransactions!
      $method
        ->setStatus(PhortunePaymentMethod::STATUS_DISABLED)
        ->save();

      return id(new AphrontRedirectResponse())->setURI($account_uri);
    }

    return $this->newDialog()
      ->setTitle(pht('Disable Payment Method?'))
      ->setShortTitle(pht('Disable Payment Method'))
      ->appendParagraph(
        pht(
          'Disable the payment method "%s"?',
          phutil_tag(
            'strong',
            array(),
            $method->getFullDisplayName())))
      ->appendParagraph(
        pht(
          'You will no longer be able to make payments using this payment '.
          'method. Disabled payment methods can not be reactivated.'))
      ->addCancelButton($account_uri)
      ->addSubmitButton(pht('Disable Payment Method'));
  }

}
