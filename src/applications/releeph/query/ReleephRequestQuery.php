<?php

final class ReleephRequestQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $requestedCommitPHIDs;
  private $commitToRevMap;
  private $ids;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function getRevisionPHID($commit_phid) {
    if ($this->commitToRevMap) {
      return idx($this->commitToRevMap, $commit_phid, null);
    }

    return null;
  }

  public function withRequestedCommitPHIDs(array $requested_commit_phids) {
    $this->requestedCommitPHIDs = $requested_commit_phids;
    return $this;
  }

  public function withRevisionPHIDs(array $revision_phids) {
    $type = PhabricatorEdgeConfig::TYPE_DREV_HAS_COMMIT;

    $edges = id(new PhabricatorEdgeQuery())
      ->withSourcePHIDs($revision_phids)
      ->withEdgeTypes(array($type))
      ->execute();

    $this->commitToRevMap = array();

    foreach ($edges as $revision_phid => $edge) {
      foreach ($edge[$type] as $commitPHID => $item) {
        $this->commitToRevMap[$commitPHID] = $revision_phid;
      }
    }

    $this->requestedCommitPHIDs = array_keys($this->commitToRevMap);
  }

  public function loadPage() {
    $table = new ReleephRequest();
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

  private function buildWhereClause(AphrontDatabaseConnection $conn_r) {
    $where = array();

    if ($this->ids) {
      $where[] = qsprintf(
        $conn_r,
        'id IN (%Ld)',
        $this->ids);
    }

    if ($this->requestedCommitPHIDs) {
      $where[] = qsprintf(
        $conn_r,
        'requestCommitPHID IN (%Ls)',
        $this->requestedCommitPHIDs);
    }

    $where[] = $this->buildPagingClause($conn_r);

    return $this->formatWhereClause($where);
  }

}
