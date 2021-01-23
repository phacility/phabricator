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
  private $responsiblePHIDs;
  private $statuses;
  private $packagePHIDs;
  private $unreachable;
  private $permanent;

  private $needAuditRequests;
  private $needAuditAuthority;
  private $auditIDs;
  private $auditorPHIDs;
  private $epochMin;
  private $epochMax;
  private $importing;
  private $ancestorsOf;

  private $needCommitData;
  private $needDrafts;
  private $needIdentities;

  private $mustFilterRefs = false;
  private $refRepository;

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
   * @{method:withRepositoryIDs}; the underlying table is keyed by ID such
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
    $this->repositoryIDs = array_unique($repository_ids);
    return $this;
  }

  public function needCommitData($need) {
    $this->needCommitData = $need;
    return $this;
  }

  public function needDrafts($need) {
    $this->needDrafts = $need;
    return $this;
  }

  public function needIdentities($need) {
    $this->needIdentities = $need;
    return $this;
  }

  public function needAuditRequests($need) {
    $this->needAuditRequests = $need;
    return $this;
  }

  public function needAuditAuthority(array $users) {
    assert_instances_of($users, 'PhabricatorUser');
    $this->needAuditAuthority = $users;
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

  public function withResponsiblePHIDs(array $responsible_phids) {
    $this->responsiblePHIDs = $responsible_phids;
    return $this;
  }

  public function withPackagePHIDs(array $package_phids) {
    $this->packagePHIDs = $package_phids;
    return $this;
  }

  public function withUnreachable($unreachable) {
    $this->unreachable = $unreachable;
    return $this;
  }

  public function withPermanent($permanent) {
    $this->permanent = $permanent;
    return $this;
  }

  public function withStatuses(array $statuses) {
    $this->statuses = $statuses;
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

  public function withAncestorsOf(array $refs) {
    $this->ancestorsOf = $refs;
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
    $table = $this->newResultObject();
    $conn = $table->establishConnection('r');

    $empty_exception = null;
    $subqueries = array();
    if ($this->responsiblePHIDs) {
      $base_authors = $this->authorPHIDs;
      $base_auditors = $this->auditorPHIDs;

      $responsible_phids = $this->responsiblePHIDs;
      if ($base_authors) {
        $all_authors = array_merge($base_authors, $responsible_phids);
      } else {
        $all_authors = $responsible_phids;
      }

      if ($base_auditors) {
        $all_auditors = array_merge($base_auditors, $responsible_phids);
      } else {
        $all_auditors = $responsible_phids;
      }

      $this->authorPHIDs = $all_authors;
      $this->auditorPHIDs = $base_auditors;
      try {
        $subqueries[] = $this->buildStandardPageQuery(
          $conn,
          $table->getTableName());
      } catch (PhabricatorEmptyQueryException $ex) {
        $empty_exception = $ex;
      }

      $this->authorPHIDs = $base_authors;
      $this->auditorPHIDs = $all_auditors;
      try {
        $subqueries[] = $this->buildStandardPageQuery(
          $conn,
          $table->getTableName());
      } catch (PhabricatorEmptyQueryException $ex) {
        $empty_exception = $ex;
      }
    } else {
      $subqueries[] = $this->buildStandardPageQuery(
        $conn,
        $table->getTableName());
    }

    if (!$subqueries) {
      throw $empty_exception;
    }

    if (count($subqueries) > 1) {
      $unions = null;
      foreach ($subqueries as $subquery) {
        if (!$unions) {
          $unions = qsprintf(
            $conn,
            '(%Q)',
            $subquery);
          continue;
        }

        $unions = qsprintf(
          $conn,
          '%Q UNION DISTINCT (%Q)',
          $unions,
          $subquery);
      }

      $query = qsprintf(
        $conn,
        '%Q %Q %Q',
        $unions,
        $this->buildOrderClause($conn, true),
        $this->buildLimitClause($conn));
    } else {
      $query = head($subqueries);
    }

    $rows = queryfx_all($conn, '%Q', $query);
    $rows = $this->didLoadRawRows($rows);

    return $table->loadAllFromArray($rows);
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
    $viewer = $this->getViewer();

    if ($this->mustFilterRefs) {
      // If this flag is set, the query has an "Ancestors Of" constraint and
      // at least one of the constraining refs had too many ancestors for us
      // to apply the constraint with a big "commitIdentifier IN (%Ls)" clause.
      // We're going to filter each page and hope we get a full result set
      // before the query overheats.

      $ancestor_list = mpull($commits, 'getCommitIdentifier');
      $ancestor_list = array_values($ancestor_list);

      foreach ($this->ancestorsOf as $ref) {
        try {
          $ancestor_list = DiffusionQuery::callConduitWithDiffusionRequest(
            $viewer,
            DiffusionRequest::newFromDictionary(
              array(
                'repository' => $this->refRepository,
                'user' => $viewer,
              )),
            'diffusion.internal.ancestors',
            array(
              'ref' => $ref,
              'commits' => $ancestor_list,
            ));
        } catch (ConduitClientException $ex) {
          throw new PhabricatorSearchConstraintException(
            $ex->getMessage());
        }

        if (!$ancestor_list) {
          break;
        }
      }

      $ancestor_list = array_fuse($ancestor_list);
      foreach ($commits as $key => $commit) {
        $identifier = $commit->getCommitIdentifier();
        if (!isset($ancestor_list[$identifier])) {
          $this->didRejectResult($commit);
          unset($commits[$key]);
        }
      }

      if (!$commits) {
        return $commits;
      }
    }

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

    if ($this->needAuditRequests) {
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

    if ($this->needIdentities) {
      $identity_phids = array_merge(
        mpull($commits, 'getAuthorIdentityPHID'),
        mpull($commits, 'getCommitterIdentityPHID'));

      $data = id(new PhabricatorRepositoryIdentityQuery())
        ->withPHIDs($identity_phids)
        ->setViewer($this->getViewer())
        ->execute();
      $data = mpull($data, null, 'getPHID');

      foreach ($commits as $commit) {
        $author_identity = idx($data, $commit->getAuthorIdentityPHID());
        $committer_identity = idx($data, $commit->getCommitterIdentityPHID());
        $commit->attachIdentities($author_identity, $committer_identity);
      }
    }

    if ($this->needDrafts) {
      PhabricatorDraftEngine::attachDrafts(
        $viewer,
        $commits);
    }

    if ($this->needAuditAuthority) {
      $authority_users = $this->needAuditAuthority;

      // NOTE: This isn't very efficient since we're running two queries per
      // user, but there's currently no way to figure out authority for
      // multiple users in one query. Today, we only ever request authority for
      // a single user and single commit, so this has no practical impact.

      // NOTE: We're querying with the viewership of query viewer, not the
      // actual users. If the viewer can't see a project or package, they
      // won't be able to see who has authority on it. This is safer than
      // showing them true authority, and should never matter today, but it
      // also doesn't seem like a significant disclosure and might be
      // reasonable to adjust later if it causes something weird or confusing
      // to happen.

      $authority_map = array();
      foreach ($authority_users as $authority_user) {
        $authority_phid = $authority_user->getPHID();
        if (!$authority_phid) {
          continue;
        }

        $result_phids = array();

        // Users have authority over themselves.
        $result_phids[] = $authority_phid;

        // Users have authority over packages they own.
        $owned_packages = id(new PhabricatorOwnersPackageQuery())
          ->setViewer($viewer)
          ->withAuthorityPHIDs(array($authority_phid))
          ->execute();
        foreach ($owned_packages as $package) {
          $result_phids[] = $package->getPHID();
        }

        // Users have authority over projects they're members of.
        $projects = id(new PhabricatorProjectQuery())
          ->setViewer($viewer)
          ->withMemberPHIDs(array($authority_phid))
          ->execute();
        foreach ($projects as $project) {
          $result_phids[] = $project->getPHID();
        }

        $result_phids = array_fuse($result_phids);

        foreach ($commits as $commit) {
          $attach_phids = $result_phids;

          // NOTE: When modifying your own commits, you act only on behalf of
          // yourself, not your packages or projects. The idea here is that you
          // can't accept your own commits. In the future, this might change or
          // depend on configuration.
          $author_phid = $commit->getAuthorPHID();
          if ($author_phid == $authority_phid) {
            $attach_phids = array($author_phid);
            $attach_phids = array_fuse($attach_phids);
          }

          $commit->attachAuditAuthority($authority_user, $attach_phids);
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

    if ($this->ancestorsOf !== null) {
      if (count($this->repositoryIDs) !== 1) {
        throw new PhabricatorSearchConstraintException(
          pht(
            'To search for commits which are ancestors of particular refs, '.
            'you must constrain the search to exactly one repository.'));
      }

      $repository_id = head($this->repositoryIDs);
      $history_limit = $this->getRawResultLimit() * 32;
      $viewer = $this->getViewer();

      $repository = id(new PhabricatorRepositoryQuery())
        ->setViewer($viewer)
        ->withIDs(array($repository_id))
        ->executeOne();

      if (!$repository) {
        throw new PhabricatorEmptyQueryException();
      }

      if ($repository->isSVN()) {
        throw new PhabricatorSearchConstraintException(
          pht(
            'Subversion does not support searching for ancestors of '.
            'a particular ref. This operation is not meaningful in '.
            'Subversion.'));
      }

      if ($repository->isHg()) {
        throw new PhabricatorSearchConstraintException(
          pht(
            'Mercurial does not currently support searching for ancestors of '.
            'a particular ref.'));
      }

      $can_constrain = true;
      $history_identifiers = array();
      foreach ($this->ancestorsOf as $key => $ref) {
        try {
          $raw_history = DiffusionQuery::callConduitWithDiffusionRequest(
            $viewer,
            DiffusionRequest::newFromDictionary(
              array(
                'repository' => $repository,
                'user' => $viewer,
              )),
            'diffusion.historyquery',
            array(
              'commit' => $ref,
              'limit' => $history_limit,
            ));
        } catch (ConduitClientException $ex) {
          throw new PhabricatorSearchConstraintException(
            $ex->getMessage());
        }

        $ref_identifiers = array();
        foreach ($raw_history['pathChanges'] as $change) {
          $ref_identifiers[] = $change['commitIdentifier'];
        }

        // If this ref had fewer total commits than the limit, we're safe to
        // apply the constraint as a large `IN (...)` query for a list of
        // commit identifiers. This is efficient.
        if ($history_limit) {
          if (count($ref_identifiers) >= $history_limit) {
            $can_constrain = false;
            break;
          }
        }

        $history_identifiers += array_fuse($ref_identifiers);
      }

      // If all refs had a small number of ancestors, we can just put the
      // constraint into the query here and we're done. Otherwise, we need
      // to filter each page after it comes out of the MySQL layer.
      if ($can_constrain) {
        $where[] = qsprintf(
          $conn,
          'commit.commitIdentifier IN (%Ls)',
          $history_identifiers);
      } else {
        $this->mustFilterRefs = true;
        $this->refRepository = $repository;
      }
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
      $author_phids = $this->authorPHIDs;
      if ($author_phids) {
        $author_phids = $this->selectPossibleAuthors($author_phids);
        if (!$author_phids) {
          throw new PhabricatorEmptyQueryException(
            pht('Author PHIDs contain no possible authors.'));
        }
      }

      $where[] = qsprintf(
        $conn,
        'commit.authorPHID IN (%Ls)',
        $author_phids);
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

      $where[] = qsprintf($conn, '%LO', $sql);
    }

    if ($this->auditIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'auditor.id IN (%Ld)',
        $this->auditIDs);
    }

    if ($this->auditorPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'auditor.auditorPHID IN (%Ls)',
        $this->auditorPHIDs);
    }

    if ($this->statuses !== null) {
      $statuses = DiffusionCommitAuditStatus::newModernKeys(
        $this->statuses);

      $where[] = qsprintf(
        $conn,
        'commit.auditStatus IN (%Ls)',
        $statuses);
    }

    if ($this->packagePHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'package.dst IN (%Ls)',
        $this->packagePHIDs);
    }

    if ($this->unreachable !== null) {
      if ($this->unreachable) {
        $where[] = qsprintf(
          $conn,
          '(commit.importStatus & %d) = %d',
          PhabricatorRepositoryCommit::IMPORTED_UNREACHABLE,
          PhabricatorRepositoryCommit::IMPORTED_UNREACHABLE);
      } else {
        $where[] = qsprintf(
          $conn,
          '(commit.importStatus & %d) = 0',
          PhabricatorRepositoryCommit::IMPORTED_UNREACHABLE);
      }
    }

    if ($this->permanent !== null) {
      if ($this->permanent) {
        $where[] = qsprintf(
          $conn,
          '(commit.importStatus & %d) = %d',
          PhabricatorRepositoryCommit::IMPORTED_PERMANENT,
          PhabricatorRepositoryCommit::IMPORTED_PERMANENT);
      } else {
        $where[] = qsprintf(
          $conn,
          '(commit.importStatus & %d) = 0',
          PhabricatorRepositoryCommit::IMPORTED_PERMANENT);
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

  private function shouldJoinAuditor() {
    return ($this->auditIDs || $this->auditorPHIDs);
  }

  private function shouldJoinOwners() {
    return (bool)$this->packagePHIDs;
  }

  protected function buildJoinClauseParts(AphrontDatabaseConnection $conn) {
    $join = parent::buildJoinClauseParts($conn);
    $audit_request = new PhabricatorRepositoryAuditRequest();

    if ($this->shouldJoinAuditor()) {
      $join[] = qsprintf(
        $conn,
        'JOIN %T auditor ON commit.phid = auditor.commitPHID',
        $audit_request->getTableName());
    }

    if ($this->shouldJoinOwners()) {
      $join[] = qsprintf(
        $conn,
        'JOIN %T package ON commit.phid = package.src
          AND package.type = %s',
        PhabricatorEdgeConfig::TABLE_NAME_EDGE,
        DiffusionCommitHasPackageEdgeType::EDGECONST);
    }

    return $join;
  }

  protected function shouldGroupQueryResultRows() {
    if ($this->shouldJoinAuditor()) {
      return true;
    }

    if ($this->shouldJoinOwners()) {
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

  protected function newPagingMapFromPartialObject($object) {
    return array(
      'id' => (int)$object->getID(),
      'epoch' => (int)$object->getEpoch(),
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

  private function selectPossibleAuthors(array $phids) {
    // See PHI1057. Select PHIDs which might possibly be commit authors from
    // a larger list of PHIDs. This primarily filters out packages and projects
    // from "Responsible Users: ..." queries. Our goal in performing this
    // filtering is to improve the performance of the final query.

    foreach ($phids as $key => $phid) {
      if (phid_get_type($phid) !== PhabricatorPeopleUserPHIDType::TYPECONST) {
        unset($phids[$key]);
      }
    }

    return $phids;
  }


}
