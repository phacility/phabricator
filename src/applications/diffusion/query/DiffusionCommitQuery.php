<?php

final class DiffusionCommitQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $authorPHIDs;
  private $defaultRepository;
  private $identifiers;
  private $repositoryIDs;
  private $repositoryPHIDs;
  private $identifierMap;

  private $needAuditRequests;
  private $auditIDs;
  private $auditorPHIDs;
  private $needsAuditByPHIDs;
  private $auditStatus;
  private $epochMin;
  private $epochMax;
  private $importing;

  const AUDIT_STATUS_ANY       = 'audit-status-any';
  const AUDIT_STATUS_OPEN      = 'audit-status-open';
  const AUDIT_STATUS_CONCERN   = 'audit-status-concern';
  const AUDIT_STATUS_ACCEPTED  = 'audit-status-accepted';
  const AUDIT_STATUS_PARTIAL   = 'audit-status-partial';

  private $needCommitData;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withAuthorPHIDs(array $phids) {
    $this->authorPHIDs = $phids;
    return $this;
  }

  /**
   * Load commits by partial or full identifiers, e.g. "rXab82393", "rX1234",
   * or "a9caf12". When an identifier matches multiple commits, they will all
   * be returned; callers should be prepared to deal with more results than
   * they queried for.
   */
  public function withIdentifiers(array $identifiers) {
    // Some workflows (like blame lookups) can pass in large numbers of
    // duplicate identifiers. We only care about unique identifiers, so
    // get rid of duplicates immediately.
    $identifiers = array_fuse($identifiers);

    $this->identifiers = $identifiers;
    return $this;
  }

  /**
   * Look up commits in a specific repository. This is a shorthand for calling
   * @{method:withDefaultRepository} and @{method:withRepositoryIDs}.
   */
  public function withRepository(PhabricatorRepository $repository) {
    $this->withDefaultRepository($repository);
    $this->withRepositoryIDs(array($repository->getID()));
    return $this;
  }

  /**
   * Look up commits in a specific repository. Prefer
   * @{method:withRepositoryIDs}; the underyling table is keyed by ID such
   * that this method requires a separate initial query to map PHID to ID.
   */
  public function withRepositoryPHIDs(array $phids) {
    $this->repositoryPHIDs = $phids;
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

  public function needCommitData($need) {
    $this->needCommitData = $need;
    return $this;
  }

  public function needAuditRequests($need) {
    $this->needAuditRequests = $need;
    return $this;
  }

  public function withAuditIDs(array $ids) {
    $this->auditIDs = $ids;
    return $this;
  }

  public function withAuditorPHIDs(array $auditor_phids) {
    $this->auditorPHIDs = $auditor_phids;
    return $this;
  }

  public function withNeedsAuditByPHIDs(array $needs_phids) {
    $this->needsAuditByPHIDs = $needs_phids;
    return $this;
  }

  public function withAuditStatus($status) {
    $this->auditStatus = $status;
    return $this;
  }

  public function withEpochRange($min, $max) {
    $this->epochMin = $min;
    $this->epochMax = $max;
    return $this;
  }

  public function withImporting($importing) {
    $this->importing = $importing;
    return $this;
  }

  public function getIdentifierMap() {
    if ($this->identifierMap === null) {
      throw new Exception(
        pht(
          'You must %s the query before accessing the identifier map.',
          'execute()'));
    }
    return $this->identifierMap;
  }

  protected function getPrimaryTableAlias() {
    return 'commit';
  }

  protected function willExecute() {
    if ($this->identifierMap === null) {
      $this->identifierMap = array();
    }
  }

  public function newResultObject() {
    return new PhabricatorRepositoryCommit();
  }

  protected function loadPage() {
    return $this->loadStandardPage($this->newResultObject());
  }

  protected function willFilterPage(array $commits) {
    $repository_ids = mpull($commits, 'getRepositoryID', 'getRepositoryID');
    $repos = id(new PhabricatorRepositoryQuery())
      ->setViewer($this->getViewer())
      ->withIDs($repository_ids)
      ->execute();

    $min_qualified = PhabricatorRepository::MINIMUM_QUALIFIED_HASH;
    $result = array();

    foreach ($commits as $key => $commit) {
      $repo = idx($repos, $commit->getRepositoryID());
      if ($repo) {
        $commit->attachRepository($repo);
      } else {
        $this->didRejectResult($commit);
        unset($commits[$key]);
        continue;
      }

      // Build the identifierMap
      if ($this->identifiers !== null) {
        $ids = $this->identifiers;
        $prefixes = array(
          'r'.$commit->getRepository()->getCallsign(),
          'r'.$commit->getRepository()->getCallsign().':',
          'R'.$commit->getRepository()->getID().':',
          '', // No prefix is valid too and will only match the commitIdentifier
        );
        $suffix = $commit->getCommitIdentifier();

        if ($commit->getRepository()->isSVN()) {
          foreach ($prefixes as $prefix) {
            if (isset($ids[$prefix.$suffix])) {
              $result[$prefix.$suffix][] = $commit;
            }
          }
        } else {
          // This awkward construction is so we can link the commits up in O(N)
          // time instead of O(N^2).
          for ($ii = $min_qualified; $ii <= strlen($suffix); $ii++) {
            $part = substr($suffix, 0, $ii);
            foreach ($prefixes as $prefix) {
              if (isset($ids[$prefix.$part])) {
                $result[$prefix.$part][] = $commit;
              }
            }
          }
        }
      }
    }

    if ($result) {
      foreach ($result as $identifier => $matching_commits) {
        if (count($matching_commits) == 1) {
          $result[$identifier] = head($matching_commits);
        } else {
          // This reference is ambiguous (it matches more than one commit) so
          // don't link it.
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
        if (!$commit_data) {
          $commit_data = new PhabricatorRepositoryCommitData();
        }
        $commit->attachCommitData($commit_data);
      }
    }

    // TODO: This should just be `needAuditRequests`, not `shouldJoinAudits()`,
    // but leave that for a future diff.

    if ($this->needAuditRequests || $this->shouldJoinAudits()) {
      $requests = id(new PhabricatorRepositoryAuditRequest())->loadAllWhere(
        'commitPHID IN (%Ls)',
        mpull($commits, 'getPHID'));

      $requests = mgroup($requests, 'getCommitPHID');
      foreach ($commits as $commit) {
        $audit_requests = idx($requests, $commit->getPHID(), array());
        $commit->attachAudits($audit_requests);
        foreach ($audit_requests as $audit_request) {
          $audit_request->attachCommit($commit);
        }
      }
    }

    return $commits;
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->repositoryPHIDs !== null) {
      $map_repositories = id(new PhabricatorRepositoryQuery())
        ->setViewer($this->getViewer())
        ->withPHIDs($this->repositoryPHIDs)
        ->execute();

      if (!$map_repositories) {
        throw new PhabricatorEmptyQueryException();
      }
      $repository_ids = mpull($map_repositories, 'getID');
      if ($this->repositoryIDs !== null) {
        $repository_ids = array_merge($repository_ids, $this->repositoryIDs);
      }
      $this->withRepositoryIDs($repository_ids);
    }

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'commit.id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn,
        'commit.phid IN (%Ls)',
        $this->phids);
    }

    if ($this->repositoryIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'commit.repositoryID IN (%Ld)',
        $this->repositoryIDs);
    }

    if ($this->authorPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'commit.authorPHID IN (%Ls)',
        $this->authorPHIDs);
    }

    if ($this->epochMin !== null) {
      $where[] = qsprintf(
        $conn,
        'commit.epoch >= %d',
        $this->epochMin);
    }

    if ($this->epochMax !== null) {
      $where[] = qsprintf(
        $conn,
        'commit.epoch <= %d',
        $this->epochMax);
    }

    if ($this->importing !== null) {
      if ($this->importing) {
        $where[] = qsprintf(
          $conn,
          '(commit.importStatus & %d) != %d',
          PhabricatorRepositoryCommit::IMPORTED_ALL,
          PhabricatorRepositoryCommit::IMPORTED_ALL);
      } else {
        $where[] = qsprintf(
          $conn,
          '(commit.importStatus & %d) = %d',
          PhabricatorRepositoryCommit::IMPORTED_ALL,
          PhabricatorRepositoryCommit::IMPORTED_ALL);
      }
    }

    if ($this->identifiers !== null) {
      $min_unqualified = PhabricatorRepository::MINIMUM_UNQUALIFIED_HASH;
      $min_qualified   = PhabricatorRepository::MINIMUM_QUALIFIED_HASH;

      $refs = array();
      $bare = array();
      foreach ($this->identifiers as $identifier) {
        $matches = null;
        preg_match('/^(?:[rR]([A-Z]+:?|[0-9]+:))?(.*)$/',
          $identifier, $matches);
        $repo = nonempty(rtrim($matches[1], ':'), null);
        $commit_identifier = nonempty($matches[2], null);

        if ($repo === null) {
          if ($this->defaultRepository) {
            $repo = $this->defaultRepository->getPHID();
          }
        }

        if ($repo === null) {
          if (strlen($commit_identifier) < $min_unqualified) {
            continue;
          }
          $bare[] = $commit_identifier;
        } else {
          $refs[] = array(
            'repository' => $repo,
            'identifier' => $commit_identifier,
          );
        }
      }

      $sql = array();

      foreach ($bare as $identifier) {
        $sql[] = qsprintf(
          $conn,
          '(commit.commitIdentifier LIKE %> AND '.
          'LENGTH(commit.commitIdentifier) = 40)',
          $identifier);
      }

      if ($refs) {
        $repositories = ipull($refs, 'repository');

        $repos = id(new PhabricatorRepositoryQuery())
          ->setViewer($this->getViewer())
          ->withIdentifiers($repositories);
        $repos->execute();

        $repos = $repos->getIdentifierMap();
        foreach ($refs as $key => $ref) {
          $repo = idx($repos, $ref['repository']);
          if (!$repo) {
            continue;
          }

          if ($repo->isSVN()) {
            if (!ctype_digit((string)$ref['identifier'])) {
              continue;
            }
            $sql[] = qsprintf(
              $conn,
              '(commit.repositoryID = %d AND commit.commitIdentifier = %s)',
              $repo->getID(),
              // NOTE: Because the 'commitIdentifier' column is a string, MySQL
              // ignores the index if we hand it an integer. Hand it a string.
              // See T3377.
              (int)$ref['identifier']);
          } else {
            if (strlen($ref['identifier']) < $min_qualified) {
              continue;
            }

            $identifier = $ref['identifier'];
            if (strlen($identifier) == 40) {
              // MySQL seems to do slightly better with this version if the
              // clause, so issue it if we have a full commit hash.
              $sql[] = qsprintf(
                $conn,
                '(commit.repositoryID = %d
                  AND commit.commitIdentifier = %s)',
                $repo->getID(),
                $identifier);
            } else {
              $sql[] = qsprintf(
                $conn,
                '(commit.repositoryID = %d
                  AND commit.commitIdentifier LIKE %>)',
                $repo->getID(),
                $identifier);
            }
          }
        }
      }

      if (!$sql) {
        // If we discarded all possible identifiers (e.g., they all referenced
        // bogus repositories or were all too short), make sure the query finds
        // nothing.
        throw new PhabricatorEmptyQueryException(
          pht('No commit identifiers.'));
      }

      $where[] = '('.implode(' OR ', $sql).')';
    }

    if ($this->auditIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'audit.id IN (%Ld)',
        $this->auditIDs);
    }

    if ($this->auditorPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'audit.auditorPHID IN (%Ls)',
        $this->auditorPHIDs);
    }

    if ($this->needsAuditByPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'needs.auditorPHID IN (%Ls)',
        $this->needsAuditByPHIDs);
    }

    $status = $this->auditStatus;
    if ($status !== null) {
      switch ($status) {
        case self::AUDIT_STATUS_PARTIAL:
          $where[] = qsprintf(
            $conn,
            'commit.auditStatus = %d',
            PhabricatorAuditCommitStatusConstants::PARTIALLY_AUDITED);
          break;
        case self::AUDIT_STATUS_ACCEPTED:
          $where[] = qsprintf(
            $conn,
            'commit.auditStatus = %d',
            PhabricatorAuditCommitStatusConstants::FULLY_AUDITED);
          break;
        case self::AUDIT_STATUS_CONCERN:
          $where[] = qsprintf(
            $conn,
            'status.auditStatus = %s',
            PhabricatorAuditStatusConstants::CONCERNED);
          break;
        case self::AUDIT_STATUS_OPEN:
          $where[] = qsprintf(
            $conn,
            'status.auditStatus in (%Ls)',
            PhabricatorAuditStatusConstants::getOpenStatusConstants());
          break;
        case self::AUDIT_STATUS_ANY:
          break;
        default:
          $valid = array(
            self::AUDIT_STATUS_ANY,
            self::AUDIT_STATUS_OPEN,
            self::AUDIT_STATUS_CONCERN,
            self::AUDIT_STATUS_ACCEPTED,
            self::AUDIT_STATUS_PARTIAL,
          );
          throw new Exception(
            pht(
              "Unknown audit status '%s'! Valid statuses are: %s.",
              $status,
              implode(', ', $valid)));
      }
    }

    return $where;
  }

  protected function didFilterResults(array $filtered) {
    if ($this->identifierMap) {
      foreach ($this->identifierMap as $name => $commit) {
        if (isset($filtered[$commit->getPHID()])) {
          unset($this->identifierMap[$name]);
        }
      }
    }
  }

  private function shouldJoinStatus() {
    return $this->auditStatus;
  }

  private function shouldJoinAudits() {
    return $this->auditIDs || $this->auditorPHIDs;
  }

  private function shouldJoinNeeds() {
    return $this->needsAuditByPHIDs;
  }

  protected function buildJoinClauseParts(AphrontDatabaseConnection $conn) {
    $join = parent::buildJoinClauseParts($conn);
    $audit_request = new PhabricatorRepositoryAuditRequest();

    if ($this->shouldJoinStatus()) {
      $join[] = qsprintf(
        $conn,
        'LEFT JOIN %T status ON commit.phid = status.commitPHID',
        $audit_request->getTableName());
    }

    if ($this->shouldJoinAudits()) {
      $join[] = qsprintf(
        $conn,
        'JOIN %T audit ON commit.phid = audit.commitPHID',
        $audit_request->getTableName());
    }

    if ($this->shouldJoinNeeds()) {
      $join[] = qsprintf(
        $conn,
        'JOIN %T needs ON commit.phid = needs.commitPHID
          AND needs.auditStatus IN (%Ls)',
        $audit_request->getTableName(),
        array(
          PhabricatorAuditStatusConstants::AUDIT_REQUESTED,
          PhabricatorAuditStatusConstants::AUDIT_REQUIRED,
        ));
    }

    return $join;
  }

  protected function shouldGroupQueryResultRows() {
    if ($this->shouldJoinStatus()) {
      return true;
    }

    if ($this->shouldJoinAudits()) {
      return true;
    }

    if ($this->shouldJoinNeeds()) {
      return true;
    }

    return parent::shouldGroupQueryResultRows();
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorDiffusionApplication';
  }

  public function getOrderableColumns() {
    return parent::getOrderableColumns() + array(
      'epoch' => array(
        'table' => $this->getPrimaryTableAlias(),
        'column' => 'epoch',
        'type' => 'int',
        'reverse' => false,
      ),
    );
  }

  protected function getPagingValueMap($cursor, array $keys) {
    $commit = $this->loadCursorObject($cursor);
    return array(
      'id' => $commit->getID(),
      'epoch' => $commit->getEpoch(),
    );
  }

  public function getBuiltinOrders() {
    $parent = parent::getBuiltinOrders();

    // Rename the default ID-based orders.
    $parent['importnew'] = array(
      'name' => pht('Import Date (Newest First)'),
    ) + $parent['newest'];

    $parent['importold'] = array(
      'name' => pht('Import Date (Oldest First)'),
    ) + $parent['oldest'];

    return array(
      'newest' => array(
        'vector' => array('epoch', 'id'),
        'name' => pht('Commit Date (Newest First)'),
      ),
      'oldest' => array(
        'vector' => array('-epoch', '-id'),
        'name' => pht('Commit Date (Oldest First)'),
      ),
    ) + $parent;
  }


}
