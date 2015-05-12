<?php

/**
 * Query symbol information (class and function names and location), returning
 * a list of matching @{class:PhabricatorRepositorySymbol} objects and possibly
 * attached data.
 *
 * @task config   Configuring the Query
 * @task exec     Executing the Query
 * @task internal Internals
 */
final class DiffusionSymbolQuery extends PhabricatorOffsetPagedQuery {

  private $viewer;
  private $context;
  private $namePrefix;
  private $name;

  private $repositoryPHIDs;
  private $language;
  private $type;

  private $needPaths;
  private $needRepositories;


/* -(  Configuring the Query  )---------------------------------------------- */

  /**
   * @task config
   */
  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  /**
   * @task config
   */
  public function getViewer() {
    return $this->viewer;
  }

  /**
   * @task config
   */
  public function setContext($context) {
    $this->context = $context;
    return $this;
  }


  /**
   * @task config
   */
  public function setName($name) {
    $this->name = $name;
    return $this;
  }


  /**
   * @task config
   */
  public function setNamePrefix($name_prefix) {
    $this->namePrefix = $name_prefix;
    return $this;
  }


  /**
   * @task config
   */
  public function withRepositoryPHIDs(array $repository_phids) {
    $this->repositoryPHIDs = $repository_phids;
    return $this;
  }


  /**
   * @task config
   */
  public function setLanguage($language) {
    $this->language = $language;
    return $this;
  }


  /**
   * @task config
   */
  public function setType($type) {
    $this->type = $type;
    return $this;
  }


  /**
   * @task config
   */
  public function needPaths($need_paths) {
    $this->needPaths = $need_paths;
    return $this;
  }


  /**
   * @task config
   */
  public function needRepositories($need_repositories) {
    $this->needRepositories = $need_repositories;
    return $this;
  }


/* -(  Executing the Query  )------------------------------------------------ */


  /**
   * @task exec
   */
  public function execute() {
    if ($this->name && $this->namePrefix) {
      throw new Exception(
        pht('You can not set both a name and a name prefix!'));
    } else if (!$this->name && !$this->namePrefix) {
      throw new Exception(
        pht('You must set a name or a name prefix!'));
    }

    $symbol = new PhabricatorRepositorySymbol();
    $conn_r = $symbol->establishConnection('r');

    $data = queryfx_all(
      $conn_r,
      'SELECT * FROM %T %Q %Q %Q',
      $symbol->getTableName(),
      $this->buildWhereClause($conn_r),
      $this->buildOrderClause($conn_r),
      $this->buildLimitClause($conn_r));

    $symbols = $symbol->loadAllFromArray($data);

    if ($symbols) {
      if ($this->needPaths) {
        $this->loadPaths($symbols);
      }
      if ($this->needRepositories) {
        $this->loadRepositories($symbols);
      }

    }

    return $symbols;
  }


/* -(  Internals  )---------------------------------------------------------- */


  /**
   * @task internal
   */
  private function buildOrderClause($conn_r) {
    return qsprintf(
      $conn_r,
      'ORDER BY symbolName ASC');
  }


  /**
   * @task internal
   */
  protected function buildWhereClause(AphrontDatabaseConnection $conn_r) {
    $where = array();

    if (isset($this->context)) {
      $where[] = qsprintf(
        $conn_r,
        'symbolContext = %s',
        $this->context);
    }

    if ($this->name) {
      $where[] = qsprintf(
        $conn_r,
        'symbolName = %s',
        $this->name);
    }

    if ($this->namePrefix) {
      $where[] = qsprintf(
        $conn_r,
        'symbolName LIKE %>',
        $this->namePrefix);
    }

    if ($this->repositoryPHIDs) {
      $where[] = qsprintf(
        $conn_r,
        'repositoryPHID IN (%Ls)',
        $this->repositoryPHIDs);
    }

    if ($this->language) {
      $where[] = qsprintf(
        $conn_r,
        'symbolLanguage = %s',
        $this->language);
    }

    if ($this->type) {
      $where[] = qsprintf(
        $conn_r,
        'symbolType = %s',
        $this->type);
    }

    return $this->formatWhereClause($where);
  }


  /**
   * @task internal
   */
  private function loadPaths(array $symbols) {
    assert_instances_of($symbols, 'PhabricatorRepositorySymbol');
    $path_map = queryfx_all(
      id(new PhabricatorRepository())->establishConnection('r'),
      'SELECT * FROM %T WHERE id IN (%Ld)',
      PhabricatorRepository::TABLE_PATH,
      mpull($symbols, 'getPathID'));
    $path_map = ipull($path_map, 'path', 'id');
    foreach ($symbols as $symbol) {
      $symbol->attachPath(idx($path_map, $symbol->getPathID()));
    }
  }


  /**
   * @task internal
   */
  private function loadRepositories(array $symbols) {
    assert_instances_of($symbols, 'PhabricatorRepositorySymbol');

    $repos = id(new PhabricatorRepositoryQuery())
      ->setViewer($this->viewer)
      ->withPHIDs(mpull($symbols, 'getRepositoryPHID'))
      ->execute();
    $repos = mpull($repos, null, 'getPHID');

    foreach ($symbols as $symbol) {
      $repository = idx($repos, $symbol->getRepositoryPHID());
      $symbol->attachRepository($repository);
    }
  }

}
