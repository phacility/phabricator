<?php

final class DiffusionPathQuery extends Phobject {

  private $pathIDs;

  public function withPathIDs(array $path_ids) {
    $this->pathIDs = $path_ids;
    return $this;
  }

  public function execute() {
    $conn = id(new PhabricatorRepository())->establishConnection('r');

    $where = $this->buildWhereClause($conn);

    $results = queryfx_all(
      $conn,
      'SELECT * FROM %T %Q',
      PhabricatorRepository::TABLE_PATH,
      $where);

    return ipull($results, null, 'id');
  }

  protected function buildWhereClause(AphrontDatabaseConnection $conn) {
    $where = array();

    if ($this->pathIDs) {
      $where[] = qsprintf(
        $conn,
        'id IN (%Ld)',
        $this->pathIDs);
    }

    if ($where) {
      return qsprintf($conn, 'WHERE %LA', $where);
    } else {
      return qsprintf($conn, '');
    }
  }

}
