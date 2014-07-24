<?php

final class PhabricatorPhortuneApplication extends PhabricatorApplication {

  public function getName() {
    return pht('Phortune');
  }

  public function getBaseURI() {
    return '/phortune/';
  }

  public function getShortDescription() {
    return pht('Accounts and Billing');
  }

  public function getIconName() {
    return 'phortune';
  }

  public function getTitleGlyph() {
    return "\xE2\x9C\x98";
  }

  public function getApplicationGroup() {
    return self::GROUP_UTILITIES;
  }

  public function isBeta() {
    return true;
  }

  public function getRoutes() {
    return array(
      '/phortune/' => array(
        '' => 'PhortuneLandingController',
        '(?P<accountID>\d+)/' => array(
          '' => 'PhortuneAccountViewController',
          'paymentmethod/' => array(
            'edit/' => 'PhortunePaymentMethodEditController',
          ),
          'buy/(?P<productID>\d+)/' => 'PhortuneProductPurchaseController',
        ),
        'cart/(?P<id>\d+)/' => array(
          '' => 'PhortuneCartViewController',
          'checkout/' => 'PhortuneCartCheckoutController',
        ),
        'account/' => array(
          '' => 'PhortuneAccountListController',
          'edit/(?:(?P<id>\d+)/)?' => 'PhortuneAccountEditController',
        ),
        'product/' => array(
          '' => 'PhortuneProductListController',
          'view/(?P<id>\d+)/' => 'PhortuneProductViewController',
          'edit/(?:(?P<id>\d+)/)?' => 'PhortuneProductEditController',
        ),
        'provider/(?P<digest>[^/]+)/(?P<action>[^/]+)/'
          => 'PhortuneProviderController',
      ),
    );
  }

}
