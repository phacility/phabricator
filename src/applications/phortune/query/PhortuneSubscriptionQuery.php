<?php

final class PhortuneSubscriptionQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $accountPHIDs;
  private $merchantPHIDs;
  private $statuses;

  private $needTriggers;

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

  public function needTriggers($need_triggers) {
    $this->needTriggers = $need_triggers;
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

    if (!$subscriptions) {
      return $subscriptions;
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

    if (!$subscriptions) {
      return $subscriptions;
    }

    $implementations = array();

    $subscription_map = mgroup($subscriptions, 'getSubscriptionClass');
    foreach ($subscription_map as $class => $class_subscriptions) {
      $sub = newv($class, array());
      $impl_objects = $sub->loadImplementationsForRefs(
        $this->getViewer(),
        mpull($class_subscriptions, 'getSubscriptionRef'));

      $implementations += mpull($impl_objects, null, 'getRef');
    }

    foreach ($subscriptions as $key => $subscription) {
      $ref = $subscription->getSubscriptionRef();
      $implementation = idx($implementations, $ref);
      if (!$implementation) {
        unset($subscriptions[$key]);
        continue;
      }
      $subscription->attachImplementation($implementation);
    }

    if (!$subscriptions) {
      return $subscriptions;
    }

    if ($this->needTriggers) {
      $trigger_phids = mpull($subscriptions, 'getTriggerPHID');
      $triggers = id(new PhabricatorWorkerTriggerQuery())
        ->setViewer($this->getViewer())
        ->withPHIDs($trigger_phids)
        ->needEvents(true)
        ->execute();
      $triggers = mpull($triggers, null, 'getPHID');
      foreach ($subscriptions as $key => $subscription) {
        $trigger = idx($triggers, $subscription->getTriggerPHID());
        if (!$trigger) {
          unset($subscriptions[$key]);
          continue;
        }
        $subscription->attachTrigger($trigger);
      }
    }

    return $subscriptions;
  }

  protected function buildWhereClause(AphrontDatabaseConnection $conn) {
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
