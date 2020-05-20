<?php

abstract class PhortuneController extends PhabricatorController {

  private function loadEnabledProvidersForMerchant(PhortuneMerchant $merchant) {
    $viewer = $this->getRequest()->getUser();

    $provider_configs = id(new PhortunePaymentProviderConfigQuery())
      ->setViewer($viewer)
      ->withMerchantPHIDs(array($merchant->getPHID()))
      ->execute();
    $providers = mpull($provider_configs, 'buildProvider', 'getID');

    foreach ($providers as $key => $provider) {
      if (!$provider->isEnabled()) {
        unset($providers[$key]);
      }
    }

    return $providers;
  }

  protected function loadCreatePaymentMethodProvidersForMerchant(
    PhortuneMerchant $merchant) {

    $providers = $this->loadEnabledProvidersForMerchant($merchant);
    foreach ($providers as $key => $provider) {
      if (!$provider->canCreatePaymentMethods()) {
        unset($providers[$key]);
        continue;
      }
    }

    return $providers;
  }

  protected function loadOneTimePaymentProvidersForMerchant(
    PhortuneMerchant $merchant) {

    $providers = $this->loadEnabledProvidersForMerchant($merchant);
    foreach ($providers as $key => $provider) {
      if (!$provider->canProcessOneTimePayments()) {
        unset($providers[$key]);
        continue;
      }
    }

    return $providers;
  }

}
