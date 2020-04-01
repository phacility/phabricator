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
        'card/(?P<id>\d+)/' => array(
          'edit/' => 'PhortunePaymentMethodEditController',
          'disable/' => 'PhortunePaymentMethodDisableController',
        ),
        'cart/(?P<id>\d+)/' => array(
          '' => 'PhortuneCartViewController',
          'checkout/' => 'PhortuneCartCheckoutController',
          '(?P<action>print)/' => 'PhortuneCartViewController',
          '(?P<action>cancel|refund)/' => 'PhortuneCartCancelController',
          'accept/' => 'PhortuneCartAcceptController',
          'void/' => 'PhortuneCartVoidController',
          'update/' => 'PhortuneCartUpdateController',
        ),
        'account/' => array(
          '' => 'PhortuneAccountListController',

          $this->getEditRoutePattern('edit/')
            => 'PhortuneAccountEditController',

          '(?P<accountID>\d+)/' => array(
            '' => 'PhortuneAccountOverviewController',
            'details/' => 'PhortuneAccountDetailsController',
            'methods/' => array(
              '' => 'PhortuneAccountPaymentMethodController',
              '(?P<id>\d+)/' => 'PhortuneAccountPaymentMethodViewController',
              'new/' => 'PhortunePaymentMethodCreateController',
            ),
            'orders/' => array(
              '' => 'PhortuneAccountOrdersController',
              $this->getQueryRoutePattern('list/')
                => 'PhortuneAccountOrderListController',
            ),
            'charges/' => array(
              '' => 'PhortuneAccountChargesController',
              $this->getQueryRoutePattern('list/')
                => 'PhortuneAccountChargeListController',
            ),
            'subscriptions/' => array(
              '' => 'PhortuneAccountSubscriptionController',
              '(?P<subscriptionID>\d+)/' => array(
                '' => 'PhortuneAccountSubscriptionViewController',
                'autopay/(?P<methodID>\d+)/'
                  => 'PhortuneAccountSubscriptionAutopayController',
                $this->getQueryRoutePattern('orders/')
                  => 'PhortuneAccountOrderListController',
              ),
            ),
            'managers/' => array(
              '' => 'PhortuneAccountManagersController',
              'add/' => 'PhortuneAccountAddManagerController',
            ),
            'addresses/' => array(
              '' => 'PhortuneAccountEmailAddressesController',
              '(?P<addressID>\d+)/' => array(
                '' => 'PhortuneAccountEmailViewController',
                'rotate/' => 'PhortuneAccountEmailRotateController',
                '(?P<action>disable|enable)/'
                  => 'PhortuneAccountEmailStatusController',
              ),
              $this->getEditRoutePattern('edit/')
                => 'PhortuneAccountEmailEditController',
            ),
          ),
        ),
        'product/' => array(
          '' => 'PhortuneProductListController',
          'view/(?P<id>\d+)/' => 'PhortuneProductViewController',
        ),
        'provider/' => array(
          '(?P<id>\d+)/(?P<action>[^/]+)/'
            => 'PhortuneProviderActionController',
        ),
        'external/(?P<addressKey>[^/]+)/(?P<accessKey>[^/]+)/' => array(
          '' => 'PhortuneExternalOverviewController',
          'unsubscribe/' => 'PhortuneExternalUnsubscribeController',
          'order/' => array(
            '(?P<orderID>[^/]+)/' => array(
              '' => 'PhortuneExternalOrderController',
              '(?P<action>print)/' => 'PhortuneExternalOrderController',
            ),
          ),
        ),
        'merchant/' => array(
          $this->getQueryRoutePattern()
            => 'PhortuneMerchantListController',
          $this->getEditRoutePattern('edit/')
            => 'PhortuneMerchantEditController',
          '(?P<merchantID>\d+)/' => array(
            '' => 'PhortuneMerchantOverviewController',
            'details/' => 'PhortuneMerchantDetailsController',
            'providers/' => array(
              '' => 'PhortuneMerchantProvidersController',
              '(?P<providerID>\d+)/' => array(
                '' => 'PhortuneMerchantProviderViewController',
                'disable/' => 'PhortuneMerchantProviderDisableController',
              ),
              $this->getEditRoutePattern('edit/')
                => 'PhortuneMerchantProviderEditController',
            ),
            'orders/' => array(
              '' => 'PhortuneMerchantOrdersController',
              $this->getQueryRoutePattern('list/')
                => 'PhortuneMerchantOrderListController',
            ),
            'picture/' => array(
              'edit/' => 'PhortuneMerchantPictureController',
            ),
            'subscriptions/' => array(
              '' => 'PhortuneMerchantSubscriptionsController',
              $this->getQueryRoutePattern('list/')
                => 'PhortuneMerchantSubscriptionListController',
            ),
            'managers/' => array(
              '' => 'PhortuneMerchantManagersController',
              'new/' => 'PhortuneMerchantAddManagerController',
            ),
          ),
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
