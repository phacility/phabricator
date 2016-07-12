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

  protected function buildWhereClause(AphrontDatabaseConnection $conn_r) {
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

    if ($this->primaryFragmentPHIDs) {
      $where[] = qsprintf(
        $conn_r,
        'primaryFragmentPHID IN (%Ls)',
        $this->primaryFragmentPHIDs);
    }

    if ($this->names) {
      $where[] = qsprintf(
        $conn_r,
        'name IN (%Ls)',
        $this->names);
    }

    $where[] = $this->buildPagingClause($conn_r);

    return $this->formatWhereClause($where);
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
