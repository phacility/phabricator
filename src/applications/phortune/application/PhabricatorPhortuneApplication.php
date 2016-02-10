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

  public function getIcon() {
    return 'fa-diamond';
  }

  public function getTitleGlyph() {
    return "\xE2\x97\x87";
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
          'subscription/' => array(
            '(?:query/(?P<queryKey>[^/]+)/)?'
              => 'PhortuneSubscriptionListController',
            'view/(?P<id>\d+)/'
              => 'PhortuneSubscriptionViewController',
            'edit/(?P<id>\d+)/'
              => 'PhortuneSubscriptionEditController',
            'order/(?P<subscriptionID>\d+)/'
              => 'PhortuneCartListController',
          ),
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
          'orders/(?P<merchantID>\d+)/(?:query/(?P<queryKey>[^/]+)/)?'
            => 'PhortuneCartListController',
          '(?P<merchantID>\d+)/' => array(
            'cart/(?P<id>\d+)/' => array(
              '' => 'PhortuneCartViewController',
              '(?P<action>cancel|refund)/' => 'PhortuneCartCancelController',
              'update/' => 'PhortuneCartUpdateController',
              'accept/' => 'PhortuneCartAcceptController',
            ),
            'subscription/' => array(
              '(?:query/(?P<queryKey>[^/]+)/)?'
                => 'PhortuneSubscriptionListController',
              'view/(?P<id>\d+)/'
                => 'PhortuneSubscriptionViewController',
              'order/(?P<subscriptionID>\d+)/'
                => 'PhortuneCartListController',
            ),
            'invoice/' => array(
              'new/' => 'PhortuneMerchantInvoiceCreateController',
            ),
          ),
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
