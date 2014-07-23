<?php

final class PhortuneCartQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;

  private $needPurchases;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
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

  private function buildWhereClause(AphrontDatabaseConnection $conn) {
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

    return $this->formatWhereClause($where);
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorPhortuneApplication';
  }

}
