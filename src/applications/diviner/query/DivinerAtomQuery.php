<?php

final class DivinerAtomQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  protected function loadPage() {
    $table = new DivinerLiveSymbol();
    $conn_r = $table->establishConnection('r');

    $data = queryfx_all(
      $conn_r,
      'SELECT * FROM %T %Q %Q %Q',
      $table->getTableName(),
      $this->buildWhereClause($conn_r),
      $this->buildOrderClause($conn_r),
      $this->buildLimitClause($conn_r));

    return $table->loadAllFromArray($data);
  }

  protected function willFilterPage(array $atoms) {
    if (!$atoms) {
      return $atoms;
    }

    $books = array_unique(mpull($atoms, 'getBookPHID'));

    $books = id(new DivinerBookQuery())
      ->setViewer($this->getViewer())
      ->withPHIDs($books)
      ->execute();
    $books = mpull($books, null, 'getPHID');

    foreach ($atoms as $key => $atom) {
      $book = idx($books, $atom->getBookPHID());
      if (!$book) {
        unset($atoms[$key]);
        continue;
      }
      $atom->attachBook($book);
    }

    return $atoms;
  }

  private function buildWhereClause(AphrontDatabaseConnection $conn_r) {
    $where = array();

    if ($this->ids) {
      $where[] = qsprintf(
        $conn_r,
        'id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids) {
      $where[] = qsprintf(
        $conn_r,
        'phid IN (%Ls)',
        $this->phids);
    }

    $where[] = $this->buildPagingClause($conn_r);

    return $this->formatWhereClause($where);
  }

}
