<?php

final class PhortuneSubscriptionWorker extends PhabricatorWorker {

  protected function doWork() {
    $subscription = $this->loadSubscription();

    $range = $this->getBillingPeriodRange($subscription);
    list($last_epoch, $next_epoch) = $range;

    $should_invoice = $subscription->shouldInvoiceForBillingPeriod(
      $last_epoch,
      $next_epoch);
    if (!$should_invoice) {
      return;
    }

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

    $currency = $subscription->getCostForBillingPeriodAsCurrency(
      $last_epoch,
      $next_epoch);

    $purchase
      ->setBasePriceAsCurrency($currency)
      ->setMetadataValue('subscriptionPHID', $subscription->getPHID())
      ->setMetadataValue('epoch.start', $last_epoch)
      ->setMetadataValue('epoch.end', $next_epoch)
      ->save();

    $cart
      ->setSubscriptionPHID($subscription->getPHID())
      ->setIsInvoice(1)
      ->save();

    $cart->activateCart();

    try {
      $issues = $this->chargeSubscription($actor, $subscription, $cart);
    } catch (Exception $ex) {
      $issues = array(
        pht(
          'There was a technical error while trying to automatically bill '.
          'this subscription: %s',
          $ex),
      );
    }

    if (!$issues) {
      // We're all done; charging the cart sends a billing email as a side
      // effect.
      return;
    }

    // We're shoving this through the CartEditor because it has all the logic
    // for sending mail about carts. This doesn't really affect the state of
    // the cart, but reduces the amount of code duplication.

    $xactions = array();
    $xactions[] = id(new PhortuneCartTransaction())
      ->setTransactionType(PhortuneCartTransaction::TYPE_INVOICED)
      ->setNewValue(true);

    $content_source = PhabricatorContentSource::newForSource(
      PhabricatorContentSource::SOURCE_PHORTUNE,
      array());

    $acting_phid = id(new PhabricatorPhortuneApplication())->getPHID();
    $editor = id(new PhortuneCartEditor())
      ->setActor($viewer)
      ->setActingAsPHID($acting_phid)
      ->setContentSource($content_source)
      ->setContinueOnMissingFields(true)
      ->setInvoiceIssues($issues)
      ->applyTransactions($cart, $xactions);
  }


  private function chargeSubscription(
    PhabricatorUser $viewer,
    PhortuneSubscription $subscription,
    PhortuneCart $cart) {

    $issues = array();
    if (!$subscription->getDefaultPaymentMethodPHID()) {
      $issues[] = pht(
        'There is no payment method associated with this subscription, so '.
        'it could not be billed automatically. Add a default payment method '.
        'to enable automatic billing.');
      return $issues;
    }

    $method = id(new PhortunePaymentMethodQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($subscription->getDefaultPaymentMethodPHID()))
      ->executeOne();
    if (!$method) {
      $issues[] = pht(
        'The payment method associated with this subscription is invalid '.
        'or out of date, so it could not be automatically billed. Update '.
        'the default payment method to enable automatic billing.');
      return $issues;
    }

    $provider = $method->buildPaymentProvider();
    $charge = $cart->willApplyCharge($viewer, $provider, $method);

    try {
      $provider->applyCharge($method, $charge);
    } catch (Exception $ex) {
      $cart->didFailCharge($charge);
      $issues[] = pht(
        'Automatic billing failed: %s',
        $ex->getMessage());
      return $issues;
    }

    $cart->didApplyCharge($charge);
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
    $this_epoch = idx($data, 'trigger.this-epoch');

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

    if (empty($data['manual'])) {
      if (PhabricatorTime::getNow() < $this_epoch) {
        throw new Exception(
          pht(
            'Refusing to generate a subscription invoice for a billing period '.
            'which ends in the future.'));
      }
    }

    return array($last_epoch, $this_epoch);
  }

}
