<?php

final class DifferentialViewStateQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $viewerPHIDs;
  private $objectPHIDs;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withViewerPHIDs(array $phids) {
    $this->viewerPHIDs = $phids;
    return $this;
  }

  public function withObjectPHIDs(array $phids) {
    $this->objectPHIDs = $phids;
    return $this;
  }

  public function newResultObject() {
    return new DifferentialViewState();
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'id IN (%Ld)',
        $this->ids);
    }

    if ($this->viewerPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'viewerPHID IN (%Ls)',
        $this->viewerPHIDs);
    }

    if ($this->objectPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'objectPHID IN (%Ls)',
        $this->objectPHIDs);
    }

    return $where;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorDifferentialApplication';
  }

}
