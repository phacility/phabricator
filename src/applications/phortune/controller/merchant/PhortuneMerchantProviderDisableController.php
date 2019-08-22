<?php

final class PhortuneMerchantProviderDisableController
  extends PhortuneMerchantController {

  protected function shouldRequireMerchantEditCapability() {
    return true;
  }

  protected function handleMerchantRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $merchant = $this->getMerchant();

    $id = $request->getURIData('providerID');

    $provider_config = id(new PhortunePaymentProviderConfigQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$provider_config) {
      return new Aphront404Response();
    }

    $merchant_id = $merchant->getID();
    $cancel_uri = $provider_config->getURI();

    $provider = $provider_config->buildProvider();

    if ($request->isFormPost()) {
      $new_status = !$provider_config->getIsEnabled();

      $xactions = array();
      $xactions[] = id(new PhortunePaymentProviderConfigTransaction())
        ->setTransactionType(
          PhortunePaymentProviderConfigTransaction::TYPE_ENABLE)
        ->setNewValue($new_status);

      $editor = id(new PhortunePaymentProviderConfigEditor())
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true)
        ->setContinueOnMissingFields(true);

      $editor->applyTransactions($provider_config, $xactions);

      return id(new AphrontRedirectResponse())->setURI($cancel_uri);
    }

    if ($provider_config->getIsEnabled()) {
      $title = pht('Disable Provider?');
      $body = pht(
        'If you disable this payment provider, users will no longer be able '.
        'to use it to make new payments.');
      $button = pht('Disable Provider');
    } else {
      $title = pht('Enable Provider?');
      $body = pht(
        'If you enable this payment provider, users will be able to use it to '.
        'make new payments.');
      $button = pht('Enable Provider');
    }

    return $this->newDialog()
      ->setTitle($title)
      ->appendParagraph($body)
      ->addSubmitButton($button)
      ->addCancelButton($cancel_uri);
  }

}
