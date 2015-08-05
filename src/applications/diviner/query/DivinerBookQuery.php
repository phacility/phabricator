<?php

final class DivinerBookQuery extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $names;
  private $nameLike;
  private $namePrefix;
  private $repositoryPHIDs;

  private $needProjectPHIDs;
  private $needRepositories;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withNameLike($name) {
    $this->nameLike = $name;
    return $this;
  }

  public function withNames(array $names) {
    $this->names = $names;
    return $this;
  }

  public function withNamePrefix($prefix) {
    $this->namePrefix = $prefix;
    return $this;
  }

  public function withRepositoryPHIDs(array $repository_phids) {
    $this->repositoryPHIDs = $repository_phids;
    return $this;
  }

  public function needProjectPHIDs($need_phids) {
    $this->needProjectPHIDs = $need_phids;
    return $this;
  }

  public function needRepositories($need_repositories) {
    $this->needRepositories = $need_repositories;
    return $this;
  }

  protected function loadPage() {
    $table = new DivinerLiveBook();
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

  protected function didFilterPage(array $books) {
    assert_instances_of($books, 'DivinerLiveBook');

    if ($this->needRepositories) {
      $repositories = id(new PhabricatorRepositoryQuery())
        ->setViewer($this->getViewer())
        ->withPHIDs(mpull($books, 'getRepositoryPHID'))
        ->execute();
      $repositories = mpull($repositories, null, 'getPHID');

      foreach ($books as $key => $book) {
        if ($book->getRepositoryPHID() === null) {
          $book->attachRepository(null);
          continue;
        }

        $repository = idx($repositories, $book->getRepositoryPHID());

        if (!$repository) {
          $this->didRejectResult($book);
          unset($books[$key]);
          continue;
        }

        $book->attachRepository($repository);
      }
    }

    if ($this->needProjectPHIDs) {
      $edge_query = id(new PhabricatorEdgeQuery())
        ->withSourcePHIDs(mpull($books, 'getPHID'))
        ->withEdgeTypes(
          array(
            PhabricatorProjectObjectHasProjectEdgeType::EDGECONST,
          ));
      $edge_query->execute();

      foreach ($books as $book) {
        $project_phids = $edge_query->getDestinationPHIDs(
          array(
            $book->getPHID(),
          ));
        $book->attachProjectPHIDs($project_phids);
      }
    }

    return $books;
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

    if (strlen($this->nameLike)) {
      $where[] = qsprintf(
        $conn_r,
        'name LIKE %~',
        $this->nameLike);
    }

    if ($this->names !== null) {
      $where[] = qsprintf(
        $conn_r,
        'name IN (%Ls)',
        $this->names);
    }

    if (strlen($this->namePrefix)) {
      $where[] = qsprintf(
        $conn_r,
        'name LIKE %>',
        $this->namePrefix);
    }

    if ($this->repositoryPHIDs !== null) {
      $where[] = qsprintf(
        $conn_r,
        'repositoryPHID IN (%Ls)',
        $this->repositoryPHIDs);
    }

    $where[] = $this->buildPagingClause($conn_r);

    return $this->formatWhereClause($where);
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorDivinerApplication';
  }

  public function getOrderableColumns() {
    return parent::getOrderableColumns() + array(
      'name' => array(
        'column' => 'name',
        'type' => 'string',
        'reverse' => true,
        'unique' => true,
      ),
    );
  }

  protected function getPagingValueMap($cursor, array $keys) {
    $book = $this->loadCursorObject($cursor);

    return array(
      'name' => $book->getName(),
    );
  }

  public function getBuiltinOrders() {
    return array(
      'name' => array(
        'vector' => array('name'),
        'name' => pht('Name'),
      ),
    ) + parent::getBuiltinOrders();
  }

}
