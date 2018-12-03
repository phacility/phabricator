<?php

final class PhragmentSnapshotQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $primaryFragmentPHIDs;
  private $names;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withPrimaryFragmentPHIDs(array $primary_fragment_phids) {
    $this->primaryFragmentPHIDs = $primary_fragment_phids;
    return $this;
  }

  public function withNames(array $names) {
    $this->names = $names;
    return $this;
  }

  protected function loadPage() {
    $table = new PhragmentSnapshot();
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

    if ($this->primaryFragmentPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'primaryFragmentPHID IN (%Ls)',
        $this->primaryFragmentPHIDs);
    }

    if ($this->names !== null) {
      $where[] = qsprintf(
        $conn,
        'name IN (%Ls)',
        $this->names);
    }

    $where[] = $this->buildPagingClause($conn);

    return $this->formatWhereClause($conn, $where);
  }

  protected function willFilterPage(array $page) {
    $fragments = array();

    $fragment_phids = array_filter(mpull($page, 'getPrimaryFragmentPHID'));
    if ($fragment_phids) {
      $fragments = id(new PhabricatorObjectQuery())
        ->setViewer($this->getViewer())
        ->withPHIDs($fragment_phids)
        ->setParentQuery($this)
        ->execute();
      $fragments = mpull($fragments, null, 'getPHID');
    }

    foreach ($page as $key => $snapshot) {
      $fragment_phid = $snapshot->getPrimaryFragmentPHID();
      if (empty($fragments[$fragment_phid])) {
        unset($page[$key]);
        continue;
      }
      $snapshot->attachPrimaryFragment($fragments[$fragment_phid]);
    }

    return $page;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorPhragmentApplication';
  }

}
