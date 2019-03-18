<?php

final class ReleephProductQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $active;
  private $ids;
  private $phids;
  private $repositoryPHIDs;

  const ORDER_ID    = 'order-id';
  const ORDER_NAME  = 'order-name';

  public function withActive($active) {
    $this->active = $active;
    return $this;
  }

  public function setOrder($order) {
    switch ($order) {
      case self::ORDER_ID:
        $this->setOrderVector(array('id'));
        break;
      case self::ORDER_NAME:
        $this->setOrderVector(array('name'));
        break;
      default:
        throw new Exception(pht('Order "%s" not supported.', $order));
    }
    return $this;
  }

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withRepositoryPHIDs(array $repository_phids) {
    $this->repositoryPHIDs = $repository_phids;
    return $this;
  }

  protected function loadPage() {
    $table = new ReleephProject();
    $conn_r = $table->establishConnection('r');

    $rows = queryfx_all(
      $conn_r,
      'SELECT * FROM %T %Q %Q %Q',
      $table->getTableName(),
      $this->buildWhereClause($conn_r),
      $this->buildOrderClause($conn_r),
      $this->buildLimitClause($conn_r));

    return $table->loadAllFromArray($rows);
  }

  protected function willFilterPage(array $projects) {
    assert_instances_of($projects, 'ReleephProject');

    $repository_phids = mpull($projects, 'getRepositoryPHID');

    $repositories = id(new PhabricatorRepositoryQuery())
      ->setViewer($this->getViewer())
      ->withPHIDs($repository_phids)
      ->execute();
    $repositories = mpull($repositories, null, 'getPHID');

    foreach ($projects as $key => $project) {
      $repo = idx($repositories, $project->getRepositoryPHID());
      if (!$repo) {
        unset($projects[$key]);
        continue;
      }
      $project->attachRepository($repo);
    }

    return $projects;
  }

  protected function buildWhereClause(AphrontDatabaseConnection $conn) {
    $where = array();

    if ($this->active !== null) {
      $where[] = qsprintf(
        $conn,
        'isActive = %d',
        (int)$this->active);
    }

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'id IN (%Ls)',
        $this->ids);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn,
        'phid IN (%Ls)',
        $this->phids);
    }

    if ($this->repositoryPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'repositoryPHID IN (%Ls)',
        $this->repositoryPHIDs);
    }

    $where[] = $this->buildPagingClause($conn);

    return $this->formatWhereClause($conn, $where);
  }

  public function getOrderableColumns() {
    return parent::getOrderableColumns() + array(
      'name' => array(
        'column' => 'name',
        'unique' => true,
        'reverse' => true,
        'type' => 'string',
      ),
    );
  }

  protected function newPagingMapFromPartialObject($object) {
    return array(
      'id' => (int)$object->getID(),
      'name' => $object->getName(),
    );
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorReleephApplication';
  }

}
