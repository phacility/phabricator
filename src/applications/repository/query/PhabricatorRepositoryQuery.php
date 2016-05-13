<?php

final class PhabricatorRepositoryQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $callsigns;
  private $types;
  private $uuids;
  private $nameContains;
  private $uris;
  private $datasourceQuery;
  private $slugs;

  private $numericIdentifiers;
  private $callsignIdentifiers;
  private $phidIdentifiers;
  private $monogramIdentifiers;
  private $slugIdentifiers;

  private $identifierMap;

  const STATUS_OPEN = 'status-open';
  const STATUS_CLOSED = 'status-closed';
  const STATUS_ALL = 'status-all';
  private $status = self::STATUS_ALL;

  const HOSTED_PHABRICATOR = 'hosted-phab';
  const HOSTED_REMOTE = 'hosted-remote';
  const HOSTED_ALL = 'hosted-all';
  private $hosted = self::HOSTED_ALL;

  private $needMostRecentCommits;
  private $needCommitCounts;
  private $needProjectPHIDs;
  private $needURIs;

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

  public function withIdentifiers(array $identifiers) {
    $identifiers = array_fuse($identifiers);

    $ids = array();
    $callsigns = array();
    $phids = array();
    $monograms = array();
    $slugs = array();

    foreach ($identifiers as $identifier) {
      if (ctype_digit((string)$identifier)) {
        $ids[$identifier] = $identifier;
        continue;
      }

      if (preg_match('/^(r[A-Z]+|R[1-9]\d*)\z/', $identifier)) {
        $monograms[$identifier] = $identifier;
        continue;
      }

      $repository_type = PhabricatorRepositoryRepositoryPHIDType::TYPECONST;
      if (phid_get_type($identifier) === $repository_type) {
        $phids[$identifier] = $identifier;
        continue;
      }

      if (preg_match('/^[A-Z]+\z/', $identifier)) {
        $callsigns[$identifier] = $identifier;
        continue;
      }

      $slugs[$identifier] = $identifier;
    }

    $this->numericIdentifiers = $ids;
    $this->callsignIdentifiers = $callsigns;
    $this->phidIdentifiers = $phids;
    $this->monogramIdentifiers = $monograms;
    $this->slugIdentifiers = $slugs;

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

  public function withURIs(array $uris) {
    $this->uris = $uris;
    return $this;
  }

  public function withDatasourceQuery($query) {
    $this->datasourceQuery = $query;
    return $this;
  }

  public function withSlugs(array $slugs) {
    $this->slugs = $slugs;
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

  public function needURIs($need_uris) {
    $this->needURIs = $need_uris;
    return $this;
  }

  public function getBuiltinOrders() {
    return array(
      'committed' => array(
        'vector' => array('committed', 'id'),
        'name' => pht('Most Recent Commit'),
      ),
      'name' => array(
        'vector' => array('name', 'id'),
        'name' => pht('Name'),
      ),
      'callsign' => array(
        'vector' => array('callsign'),
        'name' => pht('Callsign'),
      ),
      'size' => array(
        'vector' => array('size', 'id'),
        'name' => pht('Size'),
      ),
    ) + parent::getBuiltinOrders();
  }

  public function getIdentifierMap() {
    if ($this->identifierMap === null) {
      throw new PhutilInvalidStateException('execute');
    }
    return $this->identifierMap;
  }

  protected function willExecute() {
    $this->identifierMap = array();
  }

  public function newResultObject() {
    return new PhabricatorRepository();
  }

  protected function loadPage() {
    $table = $this->newResultObject();
    $data = $this->loadStandardPageRows($table);
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

  protected function willFilterPage(array $repositories) {
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
          throw new Exception(pht("Unknown hosted failed '%s'!", $hosted));
      }
    }

    // Build the identifierMap
    if ($this->numericIdentifiers) {
      foreach ($this->numericIdentifiers as $id) {
        if (isset($repositories[$id])) {
          $this->identifierMap[$id] = $repositories[$id];
        }
      }
    }

    if ($this->callsignIdentifiers) {
      $repository_callsigns = mpull($repositories, null, 'getCallsign');

      foreach ($this->callsignIdentifiers as $callsign) {
        if (isset($repository_callsigns[$callsign])) {
          $this->identifierMap[$callsign] = $repository_callsigns[$callsign];
        }
      }
    }

    if ($this->phidIdentifiers) {
      $repository_phids = mpull($repositories, null, 'getPHID');

      foreach ($this->phidIdentifiers as $phid) {
        if (isset($repository_phids[$phid])) {
          $this->identifierMap[$phid] = $repository_phids[$phid];
        }
      }
    }

    if ($this->monogramIdentifiers) {
      $monogram_map = array();
      foreach ($repositories as $repository) {
        foreach ($repository->getAllMonograms() as $monogram) {
          $monogram_map[$monogram] = $repository;
        }
      }

      foreach ($this->monogramIdentifiers as $monogram) {
        if (isset($monogram_map[$monogram])) {
          $this->identifierMap[$monogram] = $monogram_map[$monogram];
        }
      }
    }

    if ($this->slugIdentifiers) {
      $slug_map = array();
      foreach ($repositories as $repository) {
        $slug = $repository->getRepositorySlug();
        if ($slug === null) {
          continue;
        }

        $normal = phutil_utf8_strtolower($slug);
        $slug_map[$normal] = $repository;
      }

      foreach ($this->slugIdentifiers as $slug) {
        $normal = phutil_utf8_strtolower($slug);
        if (isset($slug_map[$normal])) {
          $this->identifierMap[$slug] = $slug_map[$normal];
        }
      }
    }

    return $repositories;
  }

  protected function didFilterPage(array $repositories) {
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

    $viewer = $this->getViewer();

    if ($this->needURIs) {
      $uris = id(new PhabricatorRepositoryURIQuery())
        ->setViewer($viewer)
        ->withRepositories($repositories)
        ->execute();
      $uri_groups = mgroup($uris, 'getRepositoryPHID');
      foreach ($repositories as $repository) {
        $repository_uris = idx($uri_groups, $repository->getPHID(), array());
        $repository->attachURIs($repository_uris);
      }
    }

    return $repositories;
  }

  protected function getPrimaryTableAlias() {
    return 'r';
  }

  public function getOrderableColumns() {
    return parent::getOrderableColumns() + array(
      'committed' => array(
        'table' => 's',
        'column' => 'epoch',
        'type' => 'int',
        'null' => 'tail',
      ),
      'callsign' => array(
        'table' => 'r',
        'column' => 'callsign',
        'type' => 'string',
        'unique' => true,
        'reverse' => true,
      ),
      'name' => array(
        'table' => 'r',
        'column' => 'name',
        'type' => 'string',
        'reverse' => true,
      ),
      'size' => array(
        'table' => 's',
        'column' => 'size',
        'type' => 'int',
        'null' => 'tail',
      ),
    );
  }

  protected function willExecuteCursorQuery(
    PhabricatorCursorPagedPolicyAwareQuery $query) {
    $vector = $this->getOrderVector();

    if ($vector->containsKey('committed')) {
      $query->needMostRecentCommits(true);
    }

    if ($vector->containsKey('size')) {
      $query->needCommitCounts(true);
    }
  }

  protected function getPagingValueMap($cursor, array $keys) {
    $repository = $this->loadCursorObject($cursor);

    $map = array(
      'id' => $repository->getID(),
      'callsign' => $repository->getCallsign(),
      'name' => $repository->getName(),
    );

    foreach ($keys as $key) {
      switch ($key) {
        case 'committed':
          $commit = $repository->getMostRecentCommit();
          if ($commit) {
            $map[$key] = $commit->getEpoch();
          } else {
            $map[$key] = null;
          }
          break;
        case 'size':
          $count = $repository->getCommitCount();
          if ($count) {
            $map[$key] = $count;
          } else {
            $map[$key] = null;
          }
          break;
      }
    }

    return $map;
  }

  protected function buildSelectClauseParts(AphrontDatabaseConnection $conn) {
    $parts = parent::buildSelectClauseParts($conn);

    $parts[] = 'r.*';

    if ($this->shouldJoinSummaryTable()) {
      $parts[] = 's.*';
    }

    return $parts;
  }

  protected function buildJoinClauseParts(AphrontDatabaseConnection $conn) {
    $joins = parent::buildJoinClauseParts($conn);

    if ($this->shouldJoinSummaryTable()) {
      $joins[] = qsprintf(
        $conn,
        'LEFT JOIN %T s ON r.id = s.repositoryID',
        PhabricatorRepository::TABLE_SUMMARY);
    }

    if ($this->shouldJoinURITable()) {
      $joins[] = qsprintf(
        $conn,
        'LEFT JOIN %T uri ON r.phid = uri.repositoryPHID',
        id(new PhabricatorRepositoryURIIndex())->getTableName());
    }

    return $joins;
  }

  protected function shouldGroupQueryResultRows() {
    if ($this->shouldJoinURITable()) {
      return true;
    }

    return parent::shouldGroupQueryResultRows();
  }

  private function shouldJoinURITable() {
    return ($this->uris !== null);
  }

  private function shouldJoinSummaryTable() {
    if ($this->needCommitCounts) {
      return true;
    }

    if ($this->needMostRecentCommits) {
      return true;
    }

    $vector = $this->getOrderVector();
    if ($vector->containsKey('committed')) {
      return true;
    }

    if ($vector->containsKey('size')) {
      return true;
    }

    return false;
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'r.id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn,
        'r.phid IN (%Ls)',
        $this->phids);
    }

    if ($this->callsigns !== null) {
      $where[] = qsprintf(
        $conn,
        'r.callsign IN (%Ls)',
        $this->callsigns);
    }

    if ($this->numericIdentifiers ||
      $this->callsignIdentifiers ||
      $this->phidIdentifiers ||
      $this->monogramIdentifiers ||
      $this->slugIdentifiers) {
      $identifier_clause = array();

      if ($this->numericIdentifiers) {
        $identifier_clause[] = qsprintf(
          $conn,
          'r.id IN (%Ld)',
          $this->numericIdentifiers);
      }

      if ($this->callsignIdentifiers) {
        $identifier_clause[] = qsprintf(
          $conn,
          'r.callsign IN (%Ls)',
          $this->callsignIdentifiers);
      }

      if ($this->phidIdentifiers) {
        $identifier_clause[] = qsprintf(
          $conn,
          'r.phid IN (%Ls)',
          $this->phidIdentifiers);
      }

      if ($this->monogramIdentifiers) {
        $monogram_callsigns = array();
        $monogram_ids = array();

        foreach ($this->monogramIdentifiers as $identifier) {
          if ($identifier[0] == 'r') {
            $monogram_callsigns[] = substr($identifier, 1);
          } else {
            $monogram_ids[] = substr($identifier, 1);
          }
        }

        if ($monogram_ids) {
          $identifier_clause[] = qsprintf(
            $conn,
            'r.id IN (%Ld)',
            $monogram_ids);
        }

        if ($monogram_callsigns) {
          $identifier_clause[] = qsprintf(
            $conn,
            'r.callsign IN (%Ls)',
            $monogram_callsigns);
        }
      }

      if ($this->slugIdentifiers) {
        $identifier_clause[] = qsprintf(
          $conn,
          'r.repositorySlug IN (%Ls)',
          $this->slugIdentifiers);
      }

      $where = array('('.implode(' OR ', $identifier_clause).')');
    }

    if ($this->types) {
      $where[] = qsprintf(
        $conn,
        'r.versionControlSystem IN (%Ls)',
        $this->types);
    }

    if ($this->uuids) {
      $where[] = qsprintf(
        $conn,
        'r.uuid IN (%Ls)',
        $this->uuids);
    }

    if (strlen($this->nameContains)) {
      $where[] = qsprintf(
        $conn,
        'r.name LIKE %~',
        $this->nameContains);
    }

    if (strlen($this->datasourceQuery)) {
      // This handles having "rP" match callsigns starting with "P...".
      $query = trim($this->datasourceQuery);
      if (preg_match('/^r/', $query)) {
        $callsign = substr($query, 1);
      } else {
        $callsign = $query;
      }
      $where[] = qsprintf(
        $conn,
        'r.name LIKE %> OR r.callsign LIKE %> OR r.repositorySlug LIKE %>',
        $query,
        $callsign,
        $query);
    }

    if ($this->slugs !== null) {
      $where[] = qsprintf(
        $conn,
        'r.repositorySlug IN (%Ls)',
        $this->slugs);
    }

    if ($this->uris !== null) {
      $try_uris = $this->getNormalizedURIs();
      $try_uris = array_fuse($try_uris);

      $where[] = qsprintf(
        $conn,
        'uri.repositoryURI IN (%Ls)',
        $try_uris);
    }

    return $where;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorDiffusionApplication';
  }

  private function getNormalizedURIs() {
    $normalized_uris = array();

    // Since we don't know which type of repository this URI is in the general
    // case, just generate all the normalizations. We could refine this in some
    // cases: if the query specifies VCS types, or the URI is a git-style URI
    // or an `svn+ssh` URI, we could deduce how to normalize it. However, this
    // would be more complicated and it's not clear if it matters in practice.

    $types = PhabricatorRepositoryURINormalizer::getAllURITypes();
    foreach ($this->uris as $uri) {
      foreach ($types as $type) {
        $normalized_uri = new PhabricatorRepositoryURINormalizer($type, $uri);
        $normalized_uris[] = $normalized_uri->getNormalizedURI();
      }
    }

    return array_unique($normalized_uris);
  }

}
