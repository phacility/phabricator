<?php

final class PhortuneChargeQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $accountPHIDs;
  private $cartPHIDs;
  private $statuses;

  private $needCarts;

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

  public function withCartPHIDs(array $cart_phids) {
    $this->cartPHIDs = $cart_phids;
    return $this;
  }

  public function withStatuses(array $statuses) {
    $this->statuses = $statuses;
    return $this;
  }

  public function needCarts($need_carts) {
    $this->needCarts = $need_carts;
    return $this;
  }

  protected function loadPage() {
    $table = new PhortuneCharge();
    $conn = $table->establishConnection('r');

    $rows = queryfx_all(
      $conn,
      'SELECT charge.* FROM %T charge %Q %Q %Q',
      $table->getTableName(),
      $this->buildWhereClause($conn),
      $this->buildOrderClause($conn),
      $this->buildLimitClause($conn));

    return $table->loadAllFromArray($rows);
  }

  protected function willFilterPage(array $charges) {
    $accounts = id(new PhortuneAccountQuery())
      ->setViewer($this->getViewer())
      ->setParentQuery($this)
      ->withPHIDs(mpull($charges, 'getAccountPHID'))
      ->execute();
    $accounts = mpull($accounts, null, 'getPHID');

    foreach ($charges as $key => $charge) {
      $account = idx($accounts, $charge->getAccountPHID());
      if (!$account) {
        unset($charges[$key]);
        continue;
      }
      $charge->attachAccount($account);
    }

    return $charges;
  }

  protected function didFilterPage(array $charges) {
    if ($this->needCarts) {
      $carts = id(new PhortuneCartQuery())
        ->setViewer($this->getViewer())
        ->setParentQuery($this)
        ->withPHIDs(mpull($charges, 'getCartPHID'))
        ->execute();
      $carts = mpull($carts, null, 'getPHID');

      foreach ($charges as $charge) {
        $cart = idx($carts, $charge->getCartPHID());
        $charge->attachCart($cart);
      }
    }

    return $charges;
  }

  protected function buildWhereClause(AphrontDatabaseConnection $conn) {
    $where = array();

    $where[] = $this->buildPagingClause($conn);

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'charge.id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn,
        'charge.phid IN (%Ls)',
        $this->phids);
    }

    if ($this->accountPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'charge.accountPHID IN (%Ls)',
        $this->accountPHIDs);
    }

    if ($this->cartPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'charge.cartPHID IN (%Ls)',
        $this->cartPHIDs);
    }

    if ($this->statuses !== null) {
      $where[] = qsprintf(
        $conn,
        'charge.status IN (%Ls)',
        $this->statuses);
    }

    return $this->formatWhereClause($where);
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorPhortuneApplication';
  }

}
