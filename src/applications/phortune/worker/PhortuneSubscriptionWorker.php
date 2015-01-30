<?php

final class PhortuneSubscriptionWorker extends PhabricatorWorker {

  protected function doWork() {
    $subscription = $this->loadSubscription();

    $range = $this->getBillingPeriodRange($subscription);
    list($last_epoch, $next_epoch) = $range;

    $account = $subscription->getAccount();
    $merchant = $subscription->getMerchant();

    $viewer = PhabricatorUser::getOmnipotentUser();

    $product = id(new PhortuneProductQuery())
      ->setViewer($viewer)
      ->withClassAndRef('PhortuneSubscriptionProduct', $subscription->getPHID())
      ->executeOne();

    $cart_implementation = id(new PhortuneSubscriptionCart())
      ->setSubscription($subscription);


    // TODO: This isn't really ideal. It would be better to use an application
    // actor than the original author of the subscription. In particular, if
    // someone initiates a subscription, adds some other account managers, and
    // later leaves the company, they'll continue "acting" here indefinitely.
    // However, for now, some of the stuff later in the pipeline requires a
    // valid actor with a real PHID. The subscription should eventually be
    // able to create these invoices "as" the application it is acting on
    // behalf of.
    $actor = id(new PhabricatorPeopleQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($subscription->getAuthorPHID()))
      ->executeOne();
    if (!$actor) {
      throw new Exception(pht('Failed to load actor to bill subscription!'));
    }

    $cart = $account->newCart($actor, $cart_implementation, $merchant);

    $purchase = $cart->newPurchase($actor, $product);

    // TODO: Consider allowing subscriptions to cost an amount other than one
    // dollar and twenty-three cents.
    $currency = PhortuneCurrency::newFromUserInput($actor, '1.23 USD');

    $purchase
      ->setBasePriceAsCurrency($currency)
      ->setMetadataValue('subscriptionPHID', $subscription->getPHID())
      ->save();

    $cart->setSubscriptionPHID($subscription->getPHID());
    $cart->activateCart();

    // TODO: Autocharge this, etc.; this is still mostly faked up.
    echo 'Okay, made a cart here: ';
    echo $cart->getCheckoutURI()."\n\n";
  }


  /**
   * Load the subscription to generate an invoice for.
   *
   * @return PhortuneSubscription The subscription to invoice.
   */
  private function loadSubscription() {
    $viewer = PhabricatorUser::getOmnipotentUser();

    $data = $this->getTaskData();
    $subscription_phid = idx($data, 'subscriptionPHID');

    $subscription = id(new PhortuneSubscriptionQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($subscription_phid))
      ->executeOne();
    if (!$subscription) {
      throw new PhabricatorWorkerPermanentFailureException(
        pht(
          'Failed to load subscription with PHID "%s".',
          $subscription_phid));
    }

    return $subscription;
  }


  /**
   * Get the start and end epoch timestamps for this billing period.
   *
   * @param PhortuneSubscription The subscription being billed.
   * @return pair<int, int> Beginning and end of the billing range.
   */
  private function getBillingPeriodRange(PhortuneSubscription $subscription) {
    $data = $this->getTaskData();

    $last_epoch = idx($data, 'trigger.last-epoch');
    if (!$last_epoch) {
      // If this is the first time the subscription is firing, use the
      // creation date as the start of the billing period.
      $last_epoch = $subscription->getDateCreated();
    }
    $this_epoch = idx($data, 'trigger.next-epoch');

    if (!$last_epoch || !$this_epoch) {
      throw new PhabricatorWorkerPermanentFailureException(
        pht(
          'Subscription is missing billing period information.'));
    }

    $period_length = ($this_epoch - $last_epoch);
    if ($period_length <= 0) {
      throw new PhabricatorWorkerPermanentFailureException(
        pht(
          'Subscription has invalid billing period.'));
    }

    if (PhabricatorTime::getNow() < $this_epoch) {
      throw new Exception(
        pht(
          'Refusing to generate a subscription invoice for a billing period '.
          'which ends in the future.'));
    }

    return array($last_epoch, $this_epoch);
  }

}
