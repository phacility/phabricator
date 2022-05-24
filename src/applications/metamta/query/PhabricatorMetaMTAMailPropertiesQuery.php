<?php

final class PhabricatorMetaMTAMailPropertiesQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $objectPHIDs;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withObjectPHIDs(array $object_phids) {
    $this->objectPHIDs = $object_phids;
    return $this;
  }

  public function newResultObject() {
    return new PhabricatorMetaMTAMailProperties();
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'id IN (%Ld)',
        $this->ids);
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
    return 'PhabricatorMetaMTAApplication';
  }

}
