<?php

final class DiffusionPathQuery extends Phobject {

  private $pathIDs;

  public function withPathIDs(array $path_ids) {
    $this->pathIDs = $path_ids;
    return $this;
  }

  public function execute() {
    $conn_r = id(new PhabricatorRepository())->establishConnection('r');

    $where = $this->buildWhereClause($conn_r);

    $results = queryfx_all(
      $conn_r,
      'SELECT * FROM %T %Q',
      PhabricatorRepository::TABLE_PATH,
      $where);

    return ipull($results, null, 'id');
  }

  protected function buildWhereClause(AphrontDatabaseConnection $conn_r) {
    $where = array();

    if ($this->pathIDs) {
      $where[] = qsprintf(
        $conn_r,
        'id IN (%Ld)',
        $this->pathIDs);
    }

    if ($where) {
      return 'WHERE ('.implode(') AND (', $where).')';
    } else {
      return '';
    }
  }

}
