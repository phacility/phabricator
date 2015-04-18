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
  private $auditAwaitingUser;
  private $auditStatus;

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

  /**
   * Returns true if we should join the audit table, either because we're
   * interested in the information if it's available or because matching rows
   * must always have it.
   */
  private function shouldJoinAudits() {
    return $this->auditStatus ||
           $this->rowsMustHaveAudits();
  }

  /**
   * Return true if we should `JOIN` (vs `LEFT JOIN`) the audit table, because
   * matching commits will always have audit rows.
   */
  private function rowsMustHaveAudits() {
    return
      $this->auditIDs ||
      $this->auditorPHIDs ||
      $this->auditAwaitingUser;
  }

  public function withAuditIDs(array $ids) {
    $this->auditIDs = $ids;
    return $this;
  }

  public function withAuditorPHIDs(array $auditor_phids) {
    $this->auditorPHIDs = $auditor_phids;
    return $this;
  }

  public function withAuditAwaitingUser(PhabricatorUser $user) {
    $this->auditAwaitingUser = $user;
    return $this;
  }

  public function withAuditStatus($status) {
    $this->auditStatus = $status;
    return $this;
  }

  public function getIdentifierMap() {
    if ($this->identifierMap === null) {
      throw new Exception(
        'You must execute() the query before accessing the identifier map.');
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

  protected function loadPage() {
    $table = new PhabricatorRepositoryCommit();
    $conn_r = $table->establishConnection('r');

    $data = queryfx_all(
      $conn_r,
      'SELECT commit.* FROM %T commit %Q %Q %Q %Q %Q',
      $table->getTableName(),
      $this->buildJoinClause($conn_r),
      $this->buildWhereClause($conn_r),
      $this->buildGroupClause($conn_r),
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

    $min_qualified = PhabricatorRepository::MINIMUM_QUALIFIED_HASH;
    $result = array();

    foreach ($commits as $key => $commit) {
      $repo = idx($repos, $commit->getRepositoryID());
      if ($repo) {
        $commit->attachRepository($repo);
      } else {
        unset($commits[$key]);
        continue;
      }

      // Build the identifierMap
      if ($this->identifiers !== null) {
        $ids = array_fuse($this->identifiers);
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

  protected function buildWhereClause(AphrontDatabaseConnection $conn_r) {
    $where = array();

    if ($this->repositoryPHIDs !== null) {
      $map_repositories = id (new PhabricatorRepositoryQuery())
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
        $conn_r,
        'commit.id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn_r,
        'commit.phid IN (%Ls)',
        $this->phids);
    }

    if ($this->repositoryIDs !== null) {
      $where[] = qsprintf(
        $conn_r,
        'commit.repositoryID IN (%Ld)',
        $this->repositoryIDs);
    }

    if ($this->authorPHIDs !== null) {
      $where[] = qsprintf(
        $conn_r,
        'commit.authorPHID IN (%Ls)',
        $this->authorPHIDs);
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
            $repo = $this->defaultRepository->getCallsign();
          }
        }

        if ($repo === null) {
          if (strlen($commit_identifier) < $min_unqualified) {
            continue;
          }
          $bare[] = $commit_identifier;
        } else {
          $refs[] = array(
            'callsign' => $repo,
            'identifier' => $commit_identifier,
          );
        }
      }

      $sql = array();

      foreach ($bare as $identifier) {
        $sql[] = qsprintf(
          $conn_r,
          '(commit.commitIdentifier LIKE %> AND '.
          'LENGTH(commit.commitIdentifier) = 40)',
          $identifier);
      }

      if ($refs) {
        $callsigns = ipull($refs, 'callsign');

        $repos = id(new PhabricatorRepositoryQuery())
          ->setViewer($this->getViewer())
          ->withIdentifiers($callsigns);
        $repos->execute();

        $repos = $repos->getIdentifierMap();

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
            $sql[] = qsprintf(
              $conn_r,
              '(commit.repositoryID = %d AND commit.commitIdentifier LIKE %>)',
              $repo->getID(),
              $ref['identifier']);
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
        $conn_r,
        'audit.id IN (%Ld)',
        $this->auditIDs);
    }

    if ($this->auditorPHIDs !== null) {
      $where[] = qsprintf(
        $conn_r,
        'audit.auditorPHID IN (%Ls)',
        $this->auditorPHIDs);
    }

    if ($this->auditAwaitingUser) {
      $awaiting_user_phid = $this->auditAwaitingUser->getPHID();
      // Exclude package and project audits associated with commits where
      // the user is the author.
      $where[] = qsprintf(
        $conn_r,
        '(commit.authorPHID IS NULL OR commit.authorPHID != %s)
          OR (audit.auditorPHID = %s)',
        $awaiting_user_phid,
        $awaiting_user_phid);
    }

    $status = $this->auditStatus;
    if ($status !== null) {
      switch ($status) {
        case self::AUDIT_STATUS_PARTIAL:
          $where[] = qsprintf(
            $conn_r,
            'commit.auditStatus = %d',
            PhabricatorAuditCommitStatusConstants::PARTIALLY_AUDITED);
          break;
        case self::AUDIT_STATUS_ACCEPTED:
          $where[] = qsprintf(
            $conn_r,
            'commit.auditStatus = %d',
            PhabricatorAuditCommitStatusConstants::FULLY_AUDITED);
          break;
        case self::AUDIT_STATUS_CONCERN:
          $where[] = qsprintf(
            $conn_r,
            'audit.auditStatus = %s',
            PhabricatorAuditStatusConstants::CONCERNED);
          break;
        case self::AUDIT_STATUS_OPEN:
          $where[] = qsprintf(
            $conn_r,
            'audit.auditStatus in (%Ls)',
            PhabricatorAuditStatusConstants::getOpenStatusConstants());
          if ($this->auditAwaitingUser) {
            $where[] = qsprintf(
              $conn_r,
              'awaiting.auditStatus IS NULL OR awaiting.auditStatus != %s',
              PhabricatorAuditStatusConstants::RESIGNED);
          }
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
            "Unknown audit status '{$status}'! Valid statuses are: ".
            implode(', ', $valid));
      }
    }

    $where[] = $this->buildPagingClause($conn_r);

    return $this->formatWhereClause($where);
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

  protected function buildJoinClause(AphrontDatabaseConnection $conn_r) {
    $joins = array();
    $audit_request = new PhabricatorRepositoryAuditRequest();

    if ($this->shouldJoinAudits()) {
      $joins[] = qsprintf(
        $conn_r,
        '%Q %T audit ON commit.phid = audit.commitPHID',
        ($this->rowsMustHaveAudits() ? 'JOIN' : 'LEFT JOIN'),
        $audit_request->getTableName());
    }

    if ($this->auditAwaitingUser) {
      // Join the request table on the awaiting user's requests, so we can
      // filter out package and project requests which the user has resigned
      // from.
      $joins[] = qsprintf(
        $conn_r,
        'LEFT JOIN %T awaiting ON audit.commitPHID = awaiting.commitPHID AND
        awaiting.auditorPHID = %s',
        $audit_request->getTableName(),
        $this->auditAwaitingUser->getPHID());
    }

    if ($joins) {
      return implode(' ', $joins);
    } else {
      return '';
    }
  }

  protected function buildGroupClause(AphrontDatabaseConnection $conn_r) {
    $should_group = $this->shouldJoinAudits();

    // TODO: Currently, the audit table is missing a unique key, so we may
    // require a GROUP BY if we perform this join. See T1768. This can be
    // removed once the table has the key.
    if ($this->auditAwaitingUser) {
      $should_group = true;
    }

    if ($should_group) {
      return 'GROUP BY commit.id';
    } else {
      return '';
    }
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorDiffusionApplication';
  }

}
