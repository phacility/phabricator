<?php

final class PhabricatorRepositoryQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $callsigns;
  private $types;
  private $uuids;
  private $nameContains;
  private $remoteURIs;
  private $anyProjectPHIDs;

  const STATUS_OPEN = 'status-open';
  const STATUS_CLOSED = 'status-closed';
  const STATUS_ALL = 'status-all';
  private $status = self::STATUS_ALL;

  const ORDER_CREATED = 'order-created';
  const ORDER_COMMITTED = 'order-committed';
  const ORDER_CALLSIGN = 'order-callsign';
  const ORDER_NAME = 'order-name';
  private $order = self::ORDER_CREATED;

  const HOSTED_PHABRICATOR = 'hosted-phab';
  const HOSTED_REMOTE = 'hosted-remote';
  const HOSTED_ALL = 'hosted-all';
  private $hosted = self::HOSTED_ALL;

  private $needMostRecentCommits;
  private $needCommitCounts;
  private $needProjectPHIDs;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withCallsigns(array $callsigns) {
    $this->callsigns = $callsigns;
    return $this;
  }

  public function withStatus($status) {
    $this->status = $status;
    return $this;
  }

  public function withHosted($hosted) {
    $this->hosted = $hosted;
    return $this;
  }

  public function withTypes(array $types) {
    $this->types = $types;
    return $this;
  }

  public function withUUIDs(array $uuids) {
    $this->uuids = $uuids;
    return $this;
  }

  public function withNameContains($contains) {
    $this->nameContains = $contains;
    return $this;
  }

  public function withRemoteURIs(array $uris) {
    $this->remoteURIs = $uris;
    return $this;
  }

  public function withAnyProjects(array $projects) {
    $this->anyProjectPHIDs = $projects;
    return $this;
  }

  public function needCommitCounts($need_counts) {
    $this->needCommitCounts = $need_counts;
    return $this;
  }

  public function needMostRecentCommits($need_commits) {
    $this->needMostRecentCommits = $need_commits;
    return $this;
  }

  public function needProjectPHIDs($need_phids) {
    $this->needProjectPHIDs = $need_phids;
    return $this;
  }

  public function setOrder($order) {
    $this->order = $order;
    return $this;
  }

  protected function loadPage() {
    $table = new PhabricatorRepository();
    $conn_r = $table->establishConnection('r');

    $data = queryfx_all(
      $conn_r,
      'SELECT * FROM %T r %Q %Q %Q %Q',
      $table->getTableName(),
      $this->buildJoinsClause($conn_r),
      $this->buildWhereClause($conn_r),
      $this->buildOrderClause($conn_r),
      $this->buildLimitClause($conn_r));

    $repositories = $table->loadAllFromArray($data);

    if ($this->needCommitCounts) {
      $sizes = ipull($data, 'size', 'id');
      foreach ($repositories as $id => $repository) {
        $repository->attachCommitCount(nonempty($sizes[$id], 0));
      }
    }

    if ($this->needMostRecentCommits) {
      $commit_ids = ipull($data, 'lastCommitID', 'id');
      $commit_ids = array_filter($commit_ids);
      if ($commit_ids) {
        $commits = id(new DiffusionCommitQuery())
          ->setViewer($this->getViewer())
          ->withIDs($commit_ids)
          ->execute();
      } else {
        $commits = array();
      }
      foreach ($repositories as $id => $repository) {
        $commit = null;
        if (idx($commit_ids, $id)) {
          $commit = idx($commits, $commit_ids[$id]);
        }
        $repository->attachMostRecentCommit($commit);
      }
    }

    return $repositories;
  }

  public function willFilterPage(array $repositories) {
    assert_instances_of($repositories, 'PhabricatorRepository');

    // TODO: Denormalize repository status into the PhabricatorRepository
    // table so we can do this filtering in the database.
    foreach ($repositories as $key => $repo) {
      $status = $this->status;
      switch ($status) {
        case self::STATUS_OPEN:
          if (!$repo->isTracked()) {
            unset($repositories[$key]);
          }
          break;
        case self::STATUS_CLOSED:
          if ($repo->isTracked()) {
            unset($repositories[$key]);
          }
          break;
        case self::STATUS_ALL:
          break;
        default:
          throw new Exception("Unknown status '{$status}'!");
      }

      // TODO: This should also be denormalized.
      $hosted = $this->hosted;
      switch ($hosted) {
        case self::HOSTED_PHABRICATOR:
          if (!$repo->isHosted()) {
            unset($repositories[$key]);
          }
          break;
        case self::HOSTED_REMOTE:
          if ($repo->isHosted()) {
            unset($repositories[$key]);
          }
          break;
        case self::HOSTED_ALL:
          break;
        default:
          throw new Exception("Uknown hosted failed '${hosted}'!");
      }
    }

    // TODO: Denormalize this, too.
    if ($this->remoteURIs) {
      $try_uris = $this->getNormalizedPaths();
      $try_uris = array_fuse($try_uris);
      foreach ($repositories as $key => $repository) {
        if (!isset($try_uris[$repository->getNormalizedPath()])) {
          unset($repositories[$key]);
        }
      }
    }

    return $repositories;
  }

  public function didFilterPage(array $repositories) {
    if ($this->needProjectPHIDs) {
      $type_project = PhabricatorProjectObjectHasProjectEdgeType::EDGECONST;

      $edge_query = id(new PhabricatorEdgeQuery())
        ->withSourcePHIDs(mpull($repositories, 'getPHID'))
        ->withEdgeTypes(array($type_project));
      $edge_query->execute();

      foreach ($repositories as $repository) {
        $project_phids = $edge_query->getDestinationPHIDs(
          array(
            $repository->getPHID(),
          ));
        $repository->attachProjectPHIDs($project_phids);
      }
    }

    return $repositories;
  }

  public function getReversePaging() {
    switch ($this->order) {
      case self::ORDER_CALLSIGN:
      case self::ORDER_NAME:
        return true;
    }
    return false;
  }

  protected function getPagingColumn() {
    $order = $this->order;
    switch ($order) {
      case self::ORDER_CREATED:
        return 'r.id';
      case self::ORDER_COMMITTED:
        return 's.epoch';
      case self::ORDER_CALLSIGN:
        return 'r.callsign';
      case self::ORDER_NAME:
        return 'r.name';
      default:
        throw new Exception("Unknown order '{$order}!'");
    }
  }

  private function loadCursorObject($id) {
    $query = id(new PhabricatorRepositoryQuery())
      ->setViewer($this->getPagingViewer())
      ->withIDs(array((int)$id));

    if ($this->order == self::ORDER_COMMITTED) {
      $query->needMostRecentCommits(true);
    }

    $results = $query->execute();
    return head($results);
  }

  protected function buildPagingClause(AphrontDatabaseConnection $conn_r) {
    $default = parent::buildPagingClause($conn_r);

    $before_id = $this->getBeforeID();
    $after_id = $this->getAfterID();

    if (!$before_id && !$after_id) {
      return $default;
    }

    $order = $this->order;
    if ($order == self::ORDER_CREATED) {
      return $default;
    }

    if ($before_id) {
      $cursor = $this->loadCursorObject($before_id);
    } else {
      $cursor = $this->loadCursorObject($after_id);
    }

    if (!$cursor) {
      return null;
    }

    $id_column = array(
      'name' => 'r.id',
      'type' => 'int',
      'value' => $cursor->getID(),
    );

    $columns = array();
    switch ($order) {
      case self::ORDER_COMMITTED:
        $commit = $cursor->getMostRecentCommit();
        if (!$commit) {
          return null;
        }
        $columns[] = array(
          'name' => 's.epoch',
          'type' => 'int',
          'value' => $commit->getEpoch(),
        );
        $columns[] = $id_column;
        break;
      case self::ORDER_CALLSIGN:
        $columns[] = array(
          'name' => 'r.callsign',
          'type' => 'string',
          'value' => $cursor->getCallsign(),
          'reverse' => true,
        );
        break;
      case self::ORDER_NAME:
        $columns[] = array(
          'name' => 'r.name',
          'type' => 'string',
          'value' => $cursor->getName(),
          'reverse' => true,
        );
        $columns[] = $id_column;
        break;
      default:
        throw new Exception("Unknown order '{$order}'!");
    }

    return $this->buildPagingClauseFromMultipleColumns(
      $conn_r,
      $columns,
      array(
        // TODO: Clean up the column ordering stuff and then make this
        // depend on getReversePaging().
        'reversed' => (bool)($before_id),
      ));
  }

  private function buildJoinsClause(AphrontDatabaseConnection $conn_r) {
    $joins = array();

    $join_summary_table = $this->needCommitCounts ||
                          $this->needMostRecentCommits ||
                          ($this->order == self::ORDER_COMMITTED);

    if ($join_summary_table) {
      $joins[] = qsprintf(
        $conn_r,
        'LEFT JOIN %T s ON r.id = s.repositoryID',
        PhabricatorRepository::TABLE_SUMMARY);
    }

    if ($this->anyProjectPHIDs) {
      $joins[] = qsprintf(
        $conn_r,
        'JOIN edge e ON e.src = r.phid');
    }

    return implode(' ', $joins);
  }

  private function buildWhereClause(AphrontDatabaseConnection $conn_r) {
    $where = array();

    if ($this->ids) {
      $where[] = qsprintf(
        $conn_r,
        'r.id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids) {
      $where[] = qsprintf(
        $conn_r,
        'r.phid IN (%Ls)',
        $this->phids);
    }

    if ($this->callsigns) {
      $where[] = qsprintf(
        $conn_r,
        'r.callsign IN (%Ls)',
        $this->callsigns);
    }

    if ($this->types) {
      $where[] = qsprintf(
        $conn_r,
        'r.versionControlSystem IN (%Ls)',
        $this->types);
    }

    if ($this->uuids) {
      $where[] = qsprintf(
        $conn_r,
        'r.uuid IN (%Ls)',
        $this->uuids);
    }

    if (strlen($this->nameContains)) {
      $where[] = qsprintf(
        $conn_r,
        'name LIKE %~',
        $this->nameContains);
    }

    if ($this->anyProjectPHIDs) {
      $where[] = qsprintf(
        $conn_r,
        'e.dst IN (%Ls)',
        $this->anyProjectPHIDs);
    }

    $where[] = $this->buildPagingClause($conn_r);

    return $this->formatWhereClause($where);
  }


  public function getQueryApplicationClass() {
    return 'PhabricatorDiffusionApplication';
  }

  private function getNormalizedPaths() {
    $normalized_uris = array();

    // Since we don't know which type of repository this URI is in the general
    // case, just generate all the normalizations. We could refine this in some
    // cases: if the query specifies VCS types, or the URI is a git-style URI
    // or an `svn+ssh` URI, we could deduce how to normalize it. However, this
    // would be more complicated and it's not clear if it matters in practice.

    foreach ($this->remoteURIs as $uri) {
      $normalized_uris[] = new PhabricatorRepositoryURINormalizer(
        PhabricatorRepositoryURINormalizer::TYPE_GIT,
        $uri);
      $normalized_uris[] = new PhabricatorRepositoryURINormalizer(
        PhabricatorRepositoryURINormalizer::TYPE_SVN,
        $uri);
      $normalized_uris[] = new PhabricatorRepositoryURINormalizer(
        PhabricatorRepositoryURINormalizer::TYPE_MERCURIAL,
        $uri);
    }

    return array_unique(mpull($normalized_uris, 'getNormalizedPath'));
  }

}
