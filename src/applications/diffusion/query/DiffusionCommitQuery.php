<?php

final class DiffusionCommitQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $identifiers;
  private $phids;
  private $defaultRepository;
  private $identifierMap;
  private $repositoryIDs;

  private $needCommitData;

  /**
   * Load commits by partial or full identifiers, e.g. "rXab82393", "rX1234",
   * or "a9caf12". When an identifier matches multiple commits, they will all
   * be returned; callers should be prepared to deal with more results than
   * they queried for.
   */
  public function withIdentifiers(array $identifiers) {
    $this->identifiers = $identifiers;
    return $this;
  }

  /**
   * If a default repository is provided, ambiguous commit identifiers will
   * be assumed to belong to the default repository.
   *
   * For example, "r123" appearing in a commit message in repository X is
   * likely to be unambiguously "rX123". Normally the reference would be
   * considered ambiguous, but if you provide a default repository it will
   * be correctly resolved.
   */
  public function withDefaultRepository(PhabricatorRepository $repository) {
    $this->defaultRepository = $repository;
    return $this;
  }

  public function withRepositoryIDs(array $repository_ids) {
    $this->repositoryIDs = $repository_ids;
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

  public function needCommitData($need) {
    $this->needCommitData = $need;
    return $this;
  }

  public function getIdentifierMap() {
    if ($this->identifierMap === null) {
      throw new Exception(
        "You must execute() the query before accessing the identifier map.");
    }
    return $this->identifierMap;
  }

  protected function loadPage() {
    if ($this->identifierMap === null) {
      $this->identifierMap = array();
    }

    $table = new PhabricatorRepositoryCommit();
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

  protected function willFilterPage(array $commits) {
    $repository_ids = mpull($commits, 'getRepositoryID', 'getRepositoryID');
    $repos = id(new PhabricatorRepositoryQuery())
      ->setViewer($this->getViewer())
      ->withIDs($repository_ids)
      ->execute();

    foreach ($commits as $key => $commit) {
      $repo = idx($repos, $commit->getRepositoryID());
      if ($repo) {
        $commit->attachRepository($repo);
      } else {
        unset($commits[$key]);
      }
    }

    if ($this->identifiers !== null) {
      $ids = array_fuse($this->identifiers);
      $min_qualified = PhabricatorRepository::MINIMUM_QUALIFIED_HASH;

      $result = array();
      foreach ($commits as $commit) {
        $prefix = 'r'.$commit->getRepository()->getCallsign();
        $suffix = $commit->getCommitIdentifier();

        if ($commit->getRepository()->isSVN()) {
          if (isset($ids[$prefix.$suffix])) {
            $result[$prefix.$suffix][] = $commit;
          }
        } else {
          // This awkward contruction is so we can link the commits up in O(N)
          // time instead of O(N^2).
          for ($ii = $min_qualified; $ii <= strlen($suffix); $ii++) {
            $part = substr($suffix, 0, $ii);
            if (isset($ids[$prefix.$part])) {
              $result[$prefix.$part][] = $commit;
            }
            if (isset($ids[$part])) {
              $result[$part][] = $commit;
            }
          }
        }
      }

      foreach ($result as $identifier => $matching_commits) {
        if (count($matching_commits) == 1) {
          $result[$identifier] = head($matching_commits);
        } else {
          // This reference is ambiguous (it matches more than one commit) so
          // don't link it
          unset($result[$identifier]);
        }
      }

      $this->identifierMap += $result;
    }

    return $commits;
  }

  protected function didFilterPage(array $commits) {

    if ($this->needCommitData) {
      $data = id(new PhabricatorRepositoryCommitData())->loadAllWhere(
        'commitID in (%Ld)',
        mpull($commits, 'getID'));
      $data = mpull($data, null, 'getCommitID');
      foreach ($commits as $commit) {
        $commit_data = idx($data, $commit->getID());
        $commit->attachCommitData($commit_data);
      }
    }

    return $commits;
  }

  private function buildWhereClause(AphrontDatabaseConnection $conn_r) {
    $where = array();

    if ($this->identifiers) {
      $min_unqualified = PhabricatorRepository::MINIMUM_UNQUALIFIED_HASH;
      $min_qualified   = PhabricatorRepository::MINIMUM_QUALIFIED_HASH;

      $refs = array();
      $bare = array();
      foreach ($this->identifiers as $identifier) {
        $matches = null;
        preg_match('/^(?:r([A-Z]+))?(.*)$/', $identifier, $matches);
        $repo = nonempty($matches[1], null);
        $identifier = nonempty($matches[2], null);

        if ($repo === null) {
          if ($this->defaultRepository) {
            $repo = $this->defaultRepository->getCallsign();
          }
        }

        if ($repo === null) {
          if (strlen($identifier) < $min_unqualified) {
            continue;
          }
          $bare[] = $identifier;
        } else {
          $refs[] = array(
            'callsign' => $repo,
            'identifier' => $identifier,
          );
        }
      }

      $sql = array();

      foreach ($bare as $identifier) {
        $sql[] = qsprintf(
          $conn_r,
          '(commitIdentifier LIKE %> AND LENGTH(commitIdentifier) = 40)',
          $identifier);
      }

      if ($refs) {
        $callsigns = ipull($refs, 'callsign');
        $repos = id(new PhabricatorRepositoryQuery())
          ->setViewer($this->getViewer())
          ->withCallsigns($callsigns)
          ->execute();
        $repos = mpull($repos, null, 'getCallsign');

        foreach ($refs as $key => $ref) {
          $repo = idx($repos, $ref['callsign']);
          if (!$repo) {
            continue;
          }

          if ($repo->isSVN()) {
            if (!ctype_digit($ref['identifier'])) {
              continue;
            }
            $sql[] = qsprintf(
              $conn_r,
              '(repositoryID = %d AND commitIdentifier = %s)',
              $repo->getID(),
              // NOTE: Because the 'commitIdentifier' column is a string, MySQL
              // ignores the index if we hand it an integer. Hand it a string.
              // See T3377.
              (int)$ref['identifier']);
          } else {
            if (strlen($ref['identifier']) < $min_qualified) {
              continue;
            }
            $sql[] = qsprintf(
              $conn_r,
              '(repositoryID = %d AND commitIdentifier LIKE %>)',
              $repo->getID(),
              $ref['identifier']);
          }
        }
      }

      if (!$sql) {
        // If we discarded all possible identifiers (e.g., they all referenced
        // bogus repositories or were all too short), make sure the query finds
        // nothing.
        throw new PhabricatorEmptyQueryException('No commit identifiers.');
      }

      $where[] = '('.implode(' OR ', $sql).')';
    }

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

    if ($this->repositoryIDs) {
      $where[] = qsprintf(
        $conn_r,
        'repositoryID IN (%Ld)',
        $this->repositoryIDs);
    }

    return $this->formatWhereClause($where);
  }

  public function didFilterResults(array $filtered) {
    if ($this->identifierMap) {
      foreach ($this->identifierMap as $name => $commit) {
        if (isset($filtered[$commit->getPHID()])) {
          unset($this->identifierMap[$name]);
        }
      }
    }
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorApplicationDiffusion';
  }

}
