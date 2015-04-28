<?php

final class PhortuneProductQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $refMap;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withClassAndRef($class, $ref) {
    $this->refMap = array($class => array($ref));
    return $this;
  }

  protected function loadPage() {
    $table = new PhortuneProduct();
    $conn = $table->establishConnection('r');

    $rows = queryfx_all(
      $conn,
      'SELECT * FROM %T %Q %Q %Q',
      $table->getTableName(),
      $this->buildWhereClause($conn),
      $this->buildOrderClause($conn),
      $this->buildLimitClause($conn));

    $page = $table->loadAllFromArray($rows);

    // NOTE: We're loading product implementations here, but also creating any
    // products which do not yet exist.

    $class_map = mgroup($page, 'getProductClass');
    if ($this->refMap) {
      $class_map += array_fill_keys(array_keys($this->refMap), array());
    }

    foreach ($class_map as $class => $products) {
      $refs = mpull($products, null, 'getProductRef');
      if (isset($this->refMap[$class])) {
        $refs += array_fill_keys($this->refMap[$class], null);
      }

      $implementations = newv($class, array())->loadImplementationsForRefs(
        $this->getViewer(),
        array_keys($refs));
      $implementations = mpull($implementations, null, 'getRef');

      foreach ($implementations as $ref => $implementation) {
        $product = idx($refs, $ref);
        if ($product === null) {
          // If this product does not exist yet, create it and add it to the
          // result page.
          $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
            $product = PhortuneProduct::initializeNewProduct()
              ->setProductClass($class)
              ->setProductRef($ref)
              ->save();
          unset($unguarded);

          $page[] = $product;
        }

        $product->attachImplementation($implementation);
      }
    }

    return $page;
  }

  protected function buildWhereClause(AphrontDatabaseConnection $conn) {
    $where = array();

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn,
        'phid IN (%Ls)',
        $this->phids);
    }

    if ($this->refMap !== null) {
      $sql = array();
      foreach ($this->refMap as $class => $refs) {
        foreach ($refs as $ref) {
          $sql[] = qsprintf(
            $conn,
            '(productClassKey = %s AND productRefKey = %s)',
            PhabricatorHash::digestForIndex($class),
            PhabricatorHash::digestForIndex($ref));
        }
      }
      $where[] = implode(' OR ', $sql);
    }

    $where[] = $this->buildPagingClause($conn);

    return $this->formatWhereClause($where);
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorPhortuneApplication';
  }

}
