<?php

final class PhortuneSubscriptionWorker extends PhabricatorWorker {

  protected function doWork() {
    $subscription = $this->loadSubscription();

    $range = $this->getBillingPeriodRange($subscription);
    list($last_epoch, $next_epoch) = $range;

    // TODO: Actual billing.
    echo "Bill from {$last_epoch} to {$next_epoch}.\n";
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
