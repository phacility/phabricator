<?php

final class PhortuneCartQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $accountPHIDs;
  private $merchantPHIDs;
  private $subscriptionPHIDs;
  private $statuses;
  private $invoices;

  private $needPurchases;

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

  public function withSubscriptionPHIDs(array $subscription_phids) {
    $this->subscriptionPHIDs = $subscription_phids;
    return $this;
  }

  public function withStatuses(array $statuses) {
    $this->statuses = $statuses;
    return $this;
  }


  /**
   * Include or exclude carts which represent invoices with payments due.
   *
   * @param bool `true` to select invoices; `false` to exclude invoices.
   * @return this
   */
  public function withInvoices($invoices) {
    $this->invoices = $invoices;
    return $this;
  }

  public function needPurchases($need_purchases) {
    $this->needPurchases = $need_purchases;
    return $this;
  }

  protected function loadPage() {
    $table = new PhortuneCart();
    $conn = $table->establishConnection('r');

    $rows = queryfx_all(
      $conn,
      'SELECT cart.* FROM %T cart %Q %Q %Q',
      $table->getTableName(),
      $this->buildWhereClause($conn),
      $this->buildOrderClause($conn),
      $this->buildLimitClause($conn));

    return $table->loadAllFromArray($rows);
  }

  protected function willFilterPage(array $carts) {
    $accounts = id(new PhortuneAccountQuery())
      ->setViewer($this->getViewer())
      ->withPHIDs(mpull($carts, 'getAccountPHID'))
      ->execute();
    $accounts = mpull($accounts, null, 'getPHID');

    foreach ($carts as $key => $cart) {
      $account = idx($accounts, $cart->getAccountPHID());
      if (!$account) {
        unset($carts[$key]);
        continue;
      }
      $cart->attachAccount($account);
    }

    if (!$carts) {
      return array();
    }

    $merchants = id(new PhortuneMerchantQuery())
      ->setViewer($this->getViewer())
      ->withPHIDs(mpull($carts, 'getMerchantPHID'))
      ->execute();
    $merchants = mpull($merchants, null, 'getPHID');

    foreach ($carts as $key => $cart) {
      $merchant = idx($merchants, $cart->getMerchantPHID());
      if (!$merchant) {
        unset($carts[$key]);
        continue;
      }
      $cart->attachMerchant($merchant);
    }

    if (!$carts) {
      return array();
    }

    $implementations = array();

    $cart_map = mgroup($carts, 'getCartClass');
    foreach ($cart_map as $class => $class_carts) {
      $implementations += newv($class, array())->loadImplementationsForCarts(
        $this->getViewer(),
        $class_carts);
    }

    foreach ($carts as $key => $cart) {
      $implementation = idx($implementations, $key);
      if (!$implementation) {
        unset($carts[$key]);
        continue;
      }
      $cart->attachImplementation($implementation);
    }

    return $carts;
  }

  protected function didFilterPage(array $carts) {
    if ($this->needPurchases) {
      $purchases = id(new PhortunePurchaseQuery())
        ->setViewer($this->getViewer())
        ->setParentQuery($this)
        ->withCartPHIDs(mpull($carts, 'getPHID'))
        ->execute();

      $purchases = mgroup($purchases, 'getCartPHID');
      foreach ($carts as $cart) {
        $cart->attachPurchases(idx($purchases, $cart->getPHID(), array()));
      }
    }

    return $carts;
  }

  protected function buildWhereClause(AphrontDatabaseConnection $conn) {
    $where = array();

    $where[] = $this->buildPagingClause($conn);

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'cart.id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn,
        'cart.phid IN (%Ls)',
        $this->phids);
    }

    if ($this->accountPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'cart.accountPHID IN (%Ls)',
        $this->accountPHIDs);
    }

    if ($this->merchantPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'cart.merchantPHID IN (%Ls)',
        $this->merchantPHIDs);
    }

    if ($this->subscriptionPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'cart.subscriptionPHID IN (%Ls)',
        $this->subscriptionPHIDs);
    }

    if ($this->statuses !== null) {
      $where[] = qsprintf(
        $conn,
        'cart.status IN (%Ls)',
        $this->statuses);
    }

    if ($this->invoices !== null) {
      if ($this->invoices) {
        $where[] = qsprintf(
          $conn,
          'cart.status = %s AND cart.isInvoice = 1',
          PhortuneCart::STATUS_READY);
      } else {
        $where[] = qsprintf(
          $conn,
          'cart.status != %s OR cart.isInvoice = 0',
          PhortuneCart::STATUS_READY);
      }
    }

    return $this->formatWhereClause($where);
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorPhortuneApplication';
  }

}
