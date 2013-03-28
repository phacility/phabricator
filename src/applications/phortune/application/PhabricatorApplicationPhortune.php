<?php

final class PhabricatorApplicationPhortune extends PhabricatorApplication {

  public function getBaseURI() {
    return '/phortune/';
  }

  public function getShortDescription() {
    return pht('Account and Billing');
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
        ),

        'account/' => array(
          '' => 'PhortuneAccountListController',
          'edit/(?:(?P<id>\d+)/)?' => 'PhortuneAccountEditController',
        ),
        'paymentmethod/' => array(
          '' => 'PhortunePaymentMethodListController',
          'view/(?P<id>\d+)/' => 'PhortunePaymentMethodViewController',
          'edit/(?:(?P<id>\d+)/)?' => 'PhortunePaymentMethodEditController',
        ),
        'stripe/' => array(
          'testpaymentform/' => 'PhortuneStripeTestPaymentFormController',
        ),
      ),
    );
  }

}
