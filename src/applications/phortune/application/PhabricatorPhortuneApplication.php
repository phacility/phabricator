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

  public function isPrototype() {
    return true;
  }

  public function getRoutes() {
    return array(
      '/phortune/' => array(
        '' => 'PhortuneLandingController',
        '(?P<accountID>\d+)/' => array(
          '' => 'PhortuneAccountViewController',
          'card/' => array(
            'new/' => 'PhortunePaymentMethodCreateController',
          ),
          'order/(?:query/(?P<queryKey>[^/]+)/)?'
            => 'PhortuneCartListController',
          'charge/(?:query/(?P<queryKey>[^/]+)/)?'
            => 'PhortuneChargeListController',
        ),
        'card/(?P<id>\d+)/' => array(
          'edit/' => 'PhortunePaymentMethodEditController',
          'disable/' => 'PhortunePaymentMethodDisableController',
        ),
        'cart/(?P<id>\d+)/' => array(
          '' => 'PhortuneCartViewController',
          'checkout/' => 'PhortuneCartCheckoutController',
          '(?P<action>cancel|refund)/' => 'PhortuneCartCancelController',
          'update/' => 'PhortuneCartUpdateController',
          'accept/' => 'PhortuneCartAcceptController',
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
        'provider/' => array(
          'edit/(?:(?P<id>\d+)/)?' => 'PhortuneProviderEditController',
          'disable/(?P<id>\d+)/' => 'PhortuneProviderDisableController',
          '(?P<id>\d+)/(?P<action>[^/]+)/'
            => 'PhortuneProviderActionController',
        ),
        'merchant/' => array(
          '(?:query/(?P<queryKey>[^/]+)/)?' => 'PhortuneMerchantListController',
          'edit/(?:(?P<id>\d+)/)?' => 'PhortuneMerchantEditController',
          'orders/(?P<merchantID>\d+)/(?:query/(?P<querKey>[^/]+)/)?'
            => 'PhortuneCartListController',
          '(?P<id>\d+)/' => 'PhortuneMerchantViewController',
        ),
      ),
    );
  }

  protected function getCustomCapabilities() {
    return array(
      PhortuneMerchantCapability::CAPABILITY => array(
        'caption' => pht('Merchant accounts can receive payments.'),
        'default' => PhabricatorPolicies::POLICY_ADMIN,
      ),
    );
  }

}
