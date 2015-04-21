<?php

final class PhragmentFragmentVersionQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $fragmentPHIDs;
  private $sequences;
  private $sequenceBefore;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withFragmentPHIDs(array $fragment_phids) {
    $this->fragmentPHIDs = $fragment_phids;
    return $this;
  }

  public function withSequences(array $sequences) {
    $this->sequences = $sequences;
    return $this;
  }

  public function withSequenceBefore($current) {
    $this->sequenceBefore = $current;
    return $this;
  }

  protected function loadPage() {
    $table = new PhragmentFragmentVersion();
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

    if ($this->fragmentPHIDs) {
      $where[] = qsprintf(
        $conn_r,
        'fragmentPHID IN (%Ls)',
        $this->fragmentPHIDs);
    }

    if ($this->sequences) {
      $where[] = qsprintf(
        $conn_r,
        'sequence IN (%Ld)',
        $this->sequences);
    }

    if ($this->sequenceBefore !== null) {
      $where[] = qsprintf(
        $conn_r,
        'sequence < %d',
        $this->sequenceBefore);
    }

    $where[] = $this->buildPagingClause($conn_r);

    return $this->formatWhereClause($where);
  }

  protected function willFilterPage(array $page) {
    $fragments = array();

    $fragment_phids = array_filter(mpull($page, 'getFragmentPHID'));
    if ($fragment_phids) {
      $fragments = id(new PhabricatorObjectQuery())
        ->setViewer($this->getViewer())
        ->withPHIDs($fragment_phids)
        ->setParentQuery($this)
        ->execute();
      $fragments = mpull($fragments, null, 'getPHID');
    }

    foreach ($page as $key => $version) {
      $fragment_phid = $version->getFragmentPHID();
      if (empty($fragments[$fragment_phid])) {
        unset($page[$key]);
        continue;
      }
      $version->attachFragment($fragments[$fragment_phid]);
    }

    return $page;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorPhragmentApplication';
  }
}
