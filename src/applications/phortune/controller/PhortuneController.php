<?php

abstract class PhortuneController extends PhabricatorController {

  protected function addAccountCrumb(
    $crumbs,
    PhortuneAccount $account,
    $link = true) {

    $name = $account->getName();
    $href = null;

    if ($link) {
      $href = $this->getApplicationURI($account->getID().'/');
      $crumbs->addTextCrumb($name, $href);
    } else {
      $crumbs->addTextCrumb($name);
    }
  }

  protected function addMerchantCrumb(
    $crumbs,
    PhortuneMerchant $merchant,
    $link = true) {

    $name = $merchant->getName();
    $href = null;

    $crumbs->addTextCrumb(
      pht('Merchants'),
      $this->getApplicationURI('merchant/'));

    if ($link) {
      $href = $this->getApplicationURI('merchant/'.$merchant->getID().'/');
      $crumbs->addTextCrumb($name, $href);
    } else {
      $crumbs->addTextCrumb($name);
    }
  }

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

  protected function loadMerchantAuthority() {
    $request = $this->getRequest();
    $viewer = $this->getViewer();

    $is_merchant = (bool)$request->getURIData('merchantID');
    if (!$is_merchant) {
      return null;
    }

    $merchant = id(new PhortuneMerchantQuery())
      ->setViewer($viewer)
      ->withIDs(array($request->getURIData('merchantID')))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$merchant) {
      return null;
    }

    $viewer->grantAuthority($merchant);
    return $merchant;
  }

}
