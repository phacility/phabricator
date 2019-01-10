<?php

final class PhortunePurchaseQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $cartPHIDs;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withCartPHIDs(array $cart_phids) {
    $this->cartPHIDs = $cart_phids;
    return $this;
  }

  protected function loadPage() {
    $table = new PhortunePurchase();
    $conn = $table->establishConnection('r');

    $rows = queryfx_all(
      $conn,
      'SELECT purchase.* FROM %T purchase %Q %Q %Q',
      $table->getTableName(),
      $this->buildWhereClause($conn),
      $this->buildOrderClause($conn),
      $this->buildLimitClause($conn));

    return $table->loadAllFromArray($rows);
  }

  protected function willFilterPage(array $purchases) {
    $carts = id(new PhortuneCartQuery())
      ->setViewer($this->getViewer())
      ->setParentQuery($this)
      ->withPHIDs(mpull($purchases, 'getCartPHID'))
      ->execute();
    $carts = mpull($carts, null, 'getPHID');

    foreach ($purchases as $key => $purchase) {
      $cart = idx($carts, $purchase->getCartPHID());
      if (!$cart) {
        unset($purchases[$key]);
        continue;
      }
      $purchase->attachCart($cart);
    }

    $products = id(new PhortuneProductQuery())
      ->setViewer($this->getViewer())
      ->setParentQuery($this)
      ->withPHIDs(mpull($purchases, 'getProductPHID'))
      ->execute();
    $products = mpull($products, null, 'getPHID');

    foreach ($purchases as $key => $purchase) {
      $product = idx($products, $purchase->getProductPHID());
      if (!$product) {
        unset($purchases[$key]);
        continue;
      }
      $purchase->attachProduct($product);
    }


    return $purchases;
  }

  protected function buildWhereClause(AphrontDatabaseConnection $conn) {
    $where = array();

    $where[] = $this->buildPagingClause($conn);

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'purchase.id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn,
        'purchase.phid IN (%Ls)',
        $this->phids);
    }

    if ($this->cartPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'purchase.cartPHID IN (%Ls)',
        $this->cartPHIDs);
    }

    return $this->formatWhereClause($conn, $where);
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorPhortuneApplication';
  }

}
