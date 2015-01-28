<?php

final class PhortuneSubscriptionQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $accountPHIDs;
  private $merchantPHIDs;
  private $statuses;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withAccountPHIDs(array $account_phids) {
    $this->accountPHIDs = $account_phids;
    return $this;
  }

  public function withMerchantPHIDs(array $merchant_phids) {
    $this->merchantPHIDs = $merchant_phids;
    return $this;
  }

  public function withStatuses(array $statuses) {
    $this->statuses = $statuses;
    return $this;
  }

  protected function loadPage() {
    $table = new PhortuneSubscription();
    $conn = $table->establishConnection('r');

    $rows = queryfx_all(
      $conn,
      'SELECT subscription.* FROM %T subscription %Q %Q %Q',
      $table->getTableName(),
      $this->buildWhereClause($conn),
      $this->buildOrderClause($conn),
      $this->buildLimitClause($conn));

    return $table->loadAllFromArray($rows);
  }

  protected function willFilterPage(array $subscriptions) {
    $accounts = id(new PhortuneAccountQuery())
      ->setViewer($this->getViewer())
      ->withPHIDs(mpull($subscriptions, 'getAccountPHID'))
      ->execute();
    $accounts = mpull($accounts, null, 'getPHID');

    foreach ($subscriptions as $key => $subscription) {
      $account = idx($accounts, $subscription->getAccountPHID());
      if (!$account) {
        unset($subscriptions[$key]);
        continue;
      }
      $subscription->attachAccount($account);
    }

    $merchants = id(new PhortuneMerchantQuery())
      ->setViewer($this->getViewer())
      ->withPHIDs(mpull($subscriptions, 'getMerchantPHID'))
      ->execute();
    $merchants = mpull($merchants, null, 'getPHID');

    foreach ($subscriptions as $key => $subscription) {
      $merchant = idx($merchants, $subscription->getMerchantPHID());
      if (!$merchant) {
        unset($subscriptions[$key]);
        continue;
      }
      $subscription->attachMerchant($merchant);
    }

    $implementations = array();

    $subscription_map = mgroup($subscriptions, 'getSubscriptionClass');
    foreach ($subscription_map as $class => $class_subscriptions) {
      $sub = newv($class, array());
      $implementations += $sub->loadImplementationsForSubscriptions(
        $this->getViewer(),
        $class_subscriptions);
    }

    foreach ($subscriptions as $key => $subscription) {
      $implementation = idx($implementations, $key);
      if (!$implementation) {
        unset($subscriptions[$key]);
        continue;
      }
      $subscription->attachImplementation($implementation);
    }

    return $subscriptions;
  }

  private function buildWhereClause(AphrontDatabaseConnection $conn) {
    $where = array();

    $where[] = $this->buildPagingClause($conn);

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'subscription.id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn,
        'subscription.phid IN (%Ls)',
        $this->phids);
    }

    if ($this->accountPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'subscription.accountPHID IN (%Ls)',
        $this->accountPHIDs);
    }

    if ($this->merchantPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'subscription.merchantPHID IN (%Ls)',
        $this->merchantPHIDs);
    }

    if ($this->statuses !== null) {
      $where[] = qsprintf(
        $conn,
        'subscription.status IN (%Ls)',
        $this->statuses);
    }

    return $this->formatWhereClause($where);
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorPhortuneApplication';
  }

}
