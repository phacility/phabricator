<?php

/**
 * @task config   Query Configuration
 * @task exec     Query Execution
 * @task internal Internals
 */
final class DifferentialRevisionQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $authors = array();
  private $draftAuthors = array();
  private $ccs = array();
  private $reviewers = array();
  private $revIDs = array();
  private $commitHashes = array();
  private $phids = array();
  private $responsibles = array();
  private $branches = array();
  private $repositoryPHIDs;
  private $updatedEpochMin;
  private $updatedEpochMax;
  private $statuses;
  private $isOpen;
  private $createdEpochMin;
  private $createdEpochMax;
  private $noReviewers;
  private $paths;

  const ORDER_MODIFIED      = 'order-modified';
  const ORDER_CREATED       = 'order-created';

  private $needActiveDiffs    = false;
  private $needDiffIDs        = false;
  private $needCommitPHIDs    = false;
  private $needHashes         = false;
  private $needReviewers = false;
  private $needReviewerAuthority;
  private $needDrafts;
  private $needFlags;


/* -(  Query Configuration  )------------------------------------------------ */

  /**
   * Find revisions affecting one or more items in a list of paths.
   *
   * @param list<string> List of file paths.
   * @return this
   * @task config
   */
  public function withPaths(array $paths) {
    $this->paths = $paths;
    return $this;
  }

  /**
   * Filter results to revisions authored by one of the given PHIDs. Calling
   * this function will clear anything set by previous calls to
   * @{method:withAuthors}.
   *
   * @param array List of PHIDs of authors
   * @return this
   * @task config
   */
  public function withAuthors(array $author_phids) {
    $this->authors = $author_phids;
    return $this;
  }

  /**
   * Filter results to revisions which CC one of the listed people. Calling this
   * function will clear anything set by previous calls to @{method:withCCs}.
   *
   * @param array List of PHIDs of subscribers.
   * @return this
   * @task config
   */
  public function withCCs(array $cc_phids) {
    $this->ccs = $cc_phids;
    return $this;
  }

  /**
   * Filter results to revisions that have one of the provided PHIDs as
   * reviewers. Calling this function will clear anything set by previous calls
   * to @{method:withReviewers}.
   *
   * @param array List of PHIDs of reviewers
   * @return this
   * @task config
   */
  public function withReviewers(array $reviewer_phids) {
    if ($reviewer_phids === array()) {
      throw new Exception(
        pht(
          'Empty "withReviewers()" constraint is invalid. Provide one or '.
          'more values, or remove the constraint.'));
    }

    $with_none = false;

    foreach ($reviewer_phids as $key => $phid) {
      switch ($phid) {
        case DifferentialNoReviewersDatasource::FUNCTION_TOKEN:
          $with_none = true;
          unset($reviewer_phids[$key]);
          break;
        default:
          break;
      }
    }

    $this->noReviewers = $with_none;
    if ($reviewer_phids) {
      $this->reviewers = array_values($reviewer_phids);
    }

    return $this;
  }

  /**
   * Filter results to revisions that have one of the provided commit hashes.
   * Calling this function will clear anything set by previous calls to
   * @{method:withCommitHashes}.
   *
   * @param array List of pairs <Class
   *              ArcanistDifferentialRevisionHash::HASH_$type constant,
   *              hash>
   * @return this
   * @task config
   */
  public function withCommitHashes(array $commit_hashes) {
    $this->commitHashes = $commit_hashes;
    return $this;
  }

  public function withStatuses(array $statuses) {
    $this->statuses = $statuses;
    return $this;
  }

  public function withIsOpen($is_open) {
    $this->isOpen = $is_open;
    return $this;
  }


  /**
   * Filter results to revisions on given branches.
   *
   * @param  list List of branch names.
   * @return this
   * @task config
   */
  public function withBranches(array $branches) {
    $this->branches = $branches;
    return $this;
  }


  /**
   * Filter results to only return revisions whose ids are in the given set.
   *
   * @param array List of revision ids
   * @return this
   * @task config
   */
  public function withIDs(array $ids) {
    $this->revIDs = $ids;
    return $this;
  }


  /**
   * Filter results to only return revisions whose PHIDs are in the given set.
   *
   * @param array List of revision PHIDs
   * @return this
   * @task config
   */
  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }


  /**
   * Given a set of users, filter results to return only revisions they are
   * responsible for (i.e., they are either authors or reviewers).
   *
   * @param array List of user PHIDs.
   * @return this
   * @task config
   */
  public function withResponsibleUsers(array $responsible_phids) {
    $this->responsibles = $responsible_phids;
    return $this;
  }


  public function withRepositoryPHIDs(array $repository_phids) {
    $this->repositoryPHIDs = $repository_phids;
    return $this;
  }

  public function withUpdatedEpochBetween($min, $max) {
    $this->updatedEpochMin = $min;
    $this->updatedEpochMax = $max;
    return $this;
  }

  public function withCreatedEpochBetween($min, $max) {
    $this->createdEpochMin = $min;
    $this->createdEpochMax = $max;
    return $this;
  }


  /**
   * Set whether or not the query should load the active diff for each
   * revision.
   *
   * @param bool True to load and attach diffs.
   * @return this
   * @task config
   */
  public function needActiveDiffs($need_active_diffs) {
    $this->needActiveDiffs = $need_active_diffs;
    return $this;
  }


  /**
   * Set whether or not the query should load the associated commit PHIDs for
   * each revision.
   *
   * @param bool True to load and attach diffs.
   * @return this
   * @task config
   */
  public function needCommitPHIDs($need_commit_phids) {
    $this->needCommitPHIDs = $need_commit_phids;
    return $this;
  }


  /**
   * Set whether or not the query should load associated diff IDs for each
   * revision.
   *
   * @param bool True to load and attach diff IDs.
   * @return this
   * @task config
   */
  public function needDiffIDs($need_diff_ids) {
    $this->needDiffIDs = $need_diff_ids;
    return $this;
  }


  /**
   * Set whether or not the query should load associated commit hashes for each
   * revision.
   *
   * @param bool True to load and attach commit hashes.
   * @return this
   * @task config
   */
  public function needHashes($need_hashes) {
    $this->needHashes = $need_hashes;
    return $this;
  }


  /**
   * Set whether or not the query should load associated reviewers.
   *
   * @param bool True to load and attach reviewers.
   * @return this
   * @task config
   */
  public function needReviewers($need_reviewers) {
    $this->needReviewers = $need_reviewers;
    return $this;
  }


  /**
   * Request information about the viewer's authority to act on behalf of each
   * reviewer. In particular, they have authority to act on behalf of projects
   * they are a member of.
   *
   * @param bool True to load and attach authority.
   * @return this
   * @task config
   */
  public function needReviewerAuthority($need_reviewer_authority) {
    $this->needReviewerAuthority = $need_reviewer_authority;
    return $this;
  }

  public function needFlags($need_flags) {
    $this->needFlags = $need_flags;
    return $this;
  }

  public function needDrafts($need_drafts) {
    $this->needDrafts = $need_drafts;
    return $this;
  }


/* -(  Query Execution  )---------------------------------------------------- */


  public function newResultObject() {
    return new DifferentialRevision();
  }


  /**
   * Execute the query as configured, returning matching
   * @{class:DifferentialRevision} objects.
   *
   * @return list List of matching DifferentialRevision objects.
   * @task exec
   */
  protected function loadPage() {
    $data = $this->loadData();
    $data = $this->didLoadRawRows($data);
    $table = $this->newResultObject();
    return $table->loadAllFromArray($data);
  }

  protected function willFilterPage(array $revisions) {
    $viewer = $this->getViewer();

    $repository_phids = mpull($revisions, 'getRepositoryPHID');
    $repository_phids = array_filter($repository_phids);

    $repositories = array();
    if ($repository_phids) {
      $repositories = id(new PhabricatorRepositoryQuery())
        ->setViewer($this->getViewer())
        ->withPHIDs($repository_phids)
        ->execute();
      $repositories = mpull($repositories, null, 'getPHID');
    }

    // If a revision is associated with a repository:
    //
    //   - the viewer must be able to see the repository; or
    //   - the viewer must have an automatic view capability.
    //
    // In the latter case, we'll load the revision but not load the repository.

    $can_view = PhabricatorPolicyCapability::CAN_VIEW;
    foreach ($revisions as $key => $revision) {
      $repo_phid = $revision->getRepositoryPHID();
      if (!$repo_phid) {
        // The revision has no associated repository. Attach `null` and move on.
        $revision->attachRepository(null);
        continue;
      }

      $repository = idx($repositories, $repo_phid);
      if ($repository) {
        // The revision has an associated repository, and the viewer can see
        // it. Attach it and move on.
        $revision->attachRepository($repository);
        continue;
      }

      if ($revision->hasAutomaticCapability($can_view, $viewer)) {
        // The revision has an associated repository which the viewer can not
        // see, but the viewer has an automatic capability on this revision.
        // Load the revision without attaching a repository.
        $revision->attachRepository(null);
        continue;
      }

      if ($this->getViewer()->isOmnipotent()) {
        // The viewer is omnipotent. Allow the revision to load even without
        // a repository.
        $revision->attachRepository(null);
        continue;
      }

      // The revision has an associated repository, and the viewer can't see
      // it, and the viewer has no special capabilities. Filter out this
      // revision.
      $this->didRejectResult($revision);
      unset($revisions[$key]);
    }

    if (!$revisions) {
      return array();
    }

    $table = new DifferentialRevision();
    $conn_r = $table->establishConnection('r');

    if ($this->needCommitPHIDs) {
      $this->loadCommitPHIDs($revisions);
    }

    $need_active = $this->needActiveDiffs;
    $need_ids = $need_active || $this->needDiffIDs;

    if ($need_ids) {
      $this->loadDiffIDs($conn_r, $revisions);
    }

    if ($need_active) {
      $this->loadActiveDiffs($conn_r, $revisions);
    }

    if ($this->needHashes) {
      $this->loadHashes($conn_r, $revisions);
    }

    if ($this->needReviewers || $this->needReviewerAuthority) {
      $this->loadReviewers($conn_r, $revisions);
    }

    return $revisions;
  }

  protected function didFilterPage(array $revisions) {
    $viewer = $this->getViewer();

    if ($this->needFlags) {
      $flags = id(new PhabricatorFlagQuery())
        ->setViewer($viewer)
        ->withOwnerPHIDs(array($viewer->getPHID()))
        ->withObjectPHIDs(mpull($revisions, 'getPHID'))
        ->execute();
      $flags = mpull($flags, null, 'getObjectPHID');
      foreach ($revisions as $revision) {
        $revision->attachFlag(
          $viewer,
          idx($flags, $revision->getPHID()));
      }
    }

    if ($this->needDrafts) {
      PhabricatorDraftEngine::attachDrafts(
        $viewer,
        $revisions);
    }

    return $revisions;
  }

  private function loadData() {
    $table = $this->newResultObject();
    $conn = $table->establishConnection('r');

    $selects = array();

    // NOTE: If the query includes "responsiblePHIDs", we execute it as a
    // UNION of revisions they own and revisions they're reviewing. This has
    // much better performance than doing it with JOIN/WHERE.
    if ($this->responsibles) {
      $basic_authors = $this->authors;
      $basic_reviewers = $this->reviewers;

      try {
        // Build the query where the responsible users are authors.
        $this->authors = array_merge($basic_authors, $this->responsibles);

        $this->reviewers = $basic_reviewers;
        $selects[] = $this->buildSelectStatement($conn);

        // Build the query where the responsible users are reviewers, or
        // projects they are members of are reviewers.
        $this->authors = $basic_authors;
        $this->reviewers = array_merge($basic_reviewers, $this->responsibles);
        $selects[] = $this->buildSelectStatement($conn);

        // Put everything back like it was.
        $this->authors = $basic_authors;
        $this->reviewers = $basic_reviewers;
      } catch (Exception $ex) {
        $this->authors = $basic_authors;
        $this->reviewers = $basic_reviewers;
        throw $ex;
      }
    } else {
      $selects[] = $this->buildSelectStatement($conn);
    }

    if (count($selects) > 1) {
      $unions = null;
      foreach ($selects as $select) {
        if (!$unions) {
          $unions = $select;
          continue;
        }

        $unions = qsprintf(
          $conn,
          '%Q UNION DISTINCT %Q',
          $unions,
          $select);
      }

      $query = qsprintf(
        $conn,
        '%Q %Q %Q',
        $unions,
        $this->buildOrderClause($conn, true),
        $this->buildLimitClause($conn));
    } else {
      $query = head($selects);
    }

    return queryfx_all($conn, '%Q', $query);
  }

  private function buildSelectStatement(AphrontDatabaseConnection $conn_r) {
    $table = new DifferentialRevision();

    $select = $this->buildSelectClause($conn_r);

    $from = qsprintf(
      $conn_r,
      'FROM %T r',
      $table->getTableName());

    $joins = $this->buildJoinsClause($conn_r);
    $where = $this->buildWhereClause($conn_r);
    $group_by = $this->buildGroupClause($conn_r);
    $having = $this->buildHavingClause($conn_r);

    $order_by = $this->buildOrderClause($conn_r);

    $limit = $this->buildLimitClause($conn_r);

    return qsprintf(
      $conn_r,
      '(%Q %Q %Q %Q %Q %Q %Q %Q)',
      $select,
      $from,
      $joins,
      $where,
      $group_by,
      $having,
      $order_by,
      $limit);
  }


/* -(  Internals  )---------------------------------------------------------- */


  /**
   * @task internal
   */
  private function buildJoinsClause(AphrontDatabaseConnection $conn) {
    $joins = array();

    if ($this->paths) {
      $path_table = new DifferentialAffectedPath();
      $joins[] = qsprintf(
        $conn,
        'JOIN %R paths ON paths.revisionID = r.id',
        $path_table);
    }

    if ($this->commitHashes) {
      $joins[] = qsprintf(
        $conn,
        'JOIN %T hash_rel ON hash_rel.revisionID = r.id',
        ArcanistDifferentialRevisionHash::TABLE_NAME);
    }

    if ($this->ccs) {
      $joins[] = qsprintf(
        $conn,
        'JOIN %T e_ccs ON e_ccs.src = r.phid '.
        'AND e_ccs.type = %s '.
        'AND e_ccs.dst in (%Ls)',
        PhabricatorEdgeConfig::TABLE_NAME_EDGE,
        PhabricatorObjectHasSubscriberEdgeType::EDGECONST,
        $this->ccs);
    }

    if ($this->reviewers) {
      $joins[] = qsprintf(
        $conn,
        'LEFT JOIN %T reviewer ON reviewer.revisionPHID = r.phid
          AND reviewer.reviewerStatus != %s
          AND reviewer.reviewerPHID in (%Ls)',
        id(new DifferentialReviewer())->getTableName(),
        DifferentialReviewerStatus::STATUS_RESIGNED,
        $this->reviewers);
    }

    if ($this->noReviewers) {
      $joins[] = qsprintf(
        $conn,
        'LEFT JOIN %T no_reviewer ON no_reviewer.revisionPHID = r.phid
          AND no_reviewer.reviewerStatus != %s',
        id(new DifferentialReviewer())->getTableName(),
        DifferentialReviewerStatus::STATUS_RESIGNED);
    }

    if ($this->draftAuthors) {
      $joins[] = qsprintf(
        $conn,
        'JOIN %T has_draft ON has_draft.srcPHID = r.phid
          AND has_draft.type = %s
          AND has_draft.dstPHID IN (%Ls)',
        PhabricatorEdgeConfig::TABLE_NAME_EDGE,
        PhabricatorObjectHasDraftEdgeType::EDGECONST,
        $this->draftAuthors);
    }

    $joins[] = $this->buildJoinClauseParts($conn);

    return $this->formatJoinClause($conn, $joins);
  }


  /**
   * @task internal
   */
  protected function buildWhereClause(AphrontDatabaseConnection $conn) {
    $viewer = $this->getViewer();
    $where = array();

    if ($this->paths !== null) {
      $paths = $this->paths;

      $path_map = id(new DiffusionPathIDQuery($paths))
        ->loadPathIDs();

      if (!$path_map) {
        // If none of the paths have entries in the PathID table, we can not
        // possibly find any revisions affecting them.
        throw new PhabricatorEmptyQueryException();
      }

      $where[] = qsprintf(
        $conn,
        'paths.pathID IN (%Ld)',
        array_fuse($path_map));

      // If we have repository PHIDs, additionally constrain this query to
      // try to help MySQL execute it efficiently.
      if ($this->repositoryPHIDs !== null) {
        $repositories = id(new PhabricatorRepositoryQuery())
          ->setViewer($viewer)
          ->setParentQuery($this)
          ->withPHIDs($this->repositoryPHIDs)
          ->execute();

        if (!$repositories) {
          throw new PhabricatorEmptyQueryException();
        }

        $repository_ids = mpull($repositories, 'getID');

        $where[] = qsprintf(
          $conn,
          'paths.repositoryID IN (%Ld)',
          $repository_ids);
      }
    }

    if ($this->authors) {
      $where[] = qsprintf(
        $conn,
        'r.authorPHID IN (%Ls)',
        $this->authors);
    }

    if ($this->revIDs) {
      $where[] = qsprintf(
        $conn,
        'r.id IN (%Ld)',
        $this->revIDs);
    }

    if ($this->repositoryPHIDs) {
      $where[] = qsprintf(
        $conn,
        'r.repositoryPHID IN (%Ls)',
        $this->repositoryPHIDs);
    }

    if ($this->commitHashes) {
      $hash_clauses = array();
      foreach ($this->commitHashes as $info) {
        list($type, $hash) = $info;
        $hash_clauses[] = qsprintf(
          $conn,
          '(hash_rel.type = %s AND hash_rel.hash = %s)',
          $type,
          $hash);
      }
      $hash_clauses = qsprintf($conn, '%LO', $hash_clauses);
      $where[] = $hash_clauses;
    }

    if ($this->phids) {
      $where[] = qsprintf(
        $conn,
        'r.phid IN (%Ls)',
        $this->phids);
    }

    if ($this->branches) {
      $where[] = qsprintf(
        $conn,
        'r.branchName in (%Ls)',
        $this->branches);
    }

    if ($this->updatedEpochMin !== null) {
      $where[] = qsprintf(
        $conn,
        'r.dateModified >= %d',
        $this->updatedEpochMin);
    }

    if ($this->updatedEpochMax !== null) {
      $where[] = qsprintf(
        $conn,
        'r.dateModified <= %d',
        $this->updatedEpochMax);
    }

    if ($this->createdEpochMin !== null) {
      $where[] = qsprintf(
        $conn,
        'r.dateCreated >= %d',
        $this->createdEpochMin);
    }

    if ($this->createdEpochMax !== null) {
      $where[] = qsprintf(
        $conn,
        'r.dateCreated <= %d',
        $this->createdEpochMax);
    }

    if ($this->statuses !== null) {
      $where[] = qsprintf(
        $conn,
        'r.status in (%Ls)',
        $this->statuses);
    }

    if ($this->isOpen !== null) {
      if ($this->isOpen) {
        $statuses = DifferentialLegacyQuery::getModernValues(
          DifferentialLegacyQuery::STATUS_OPEN);
      } else {
        $statuses = DifferentialLegacyQuery::getModernValues(
          DifferentialLegacyQuery::STATUS_CLOSED);
      }
      $where[] = qsprintf(
        $conn,
        'r.status in (%Ls)',
        $statuses);
    }

    $reviewer_subclauses = array();

    if ($this->noReviewers) {
      $reviewer_subclauses[] = qsprintf(
        $conn,
        'no_reviewer.reviewerPHID IS NULL');
    }

    if ($this->reviewers) {
      $reviewer_subclauses[] = qsprintf(
        $conn,
        'reviewer.reviewerPHID IS NOT NULL');
    }

    if ($reviewer_subclauses) {
      $where[] = qsprintf($conn, '%LO', $reviewer_subclauses);
    }

    $where[] = $this->buildWhereClauseParts($conn);

    return $this->formatWhereClause($conn, $where);
  }


  /**
   * @task internal
   */
  protected function shouldGroupQueryResultRows() {

    if ($this->paths) {
      // (If we have exactly one repository and exactly one path, we don't
      // technically need to group, but it's simpler to always group.)
      return true;
    }

    if (count($this->ccs) > 1) {
      return true;
    }

    if (count($this->reviewers) > 1) {
      return true;
    }

    if (count($this->commitHashes) > 1) {
      return true;
    }

    if ($this->noReviewers) {
      return true;
    }

    return parent::shouldGroupQueryResultRows();
  }

  public function getBuiltinOrders() {
    $orders = parent::getBuiltinOrders() + array(
      'updated' => array(
        'vector' => array('updated', 'id'),
        'name' => pht('Date Updated (Latest First)'),
        'aliases' => array(self::ORDER_MODIFIED),
      ),
      'outdated' => array(
        'vector' => array('-updated', '-id'),
        'name' => pht('Date Updated (Oldest First)'),
       ),
    );

    // Alias the "newest" builtin to the historical key for it.
    $orders['newest']['aliases'][] = self::ORDER_CREATED;

    return $orders;
  }

  protected function getDefaultOrderVector() {
    return array('updated', 'id');
  }

  public function getOrderableColumns() {
    return array(
      'updated' => array(
        'table' => $this->getPrimaryTableAlias(),
        'column' => 'dateModified',
        'type' => 'int',
      ),
    ) + parent::getOrderableColumns();
  }

  protected function newPagingMapFromPartialObject($object) {
    return array(
      'id' => (int)$object->getID(),
      'updated' => (int)$object->getDateModified(),
    );
  }

  private function loadCommitPHIDs(array $revisions) {
    assert_instances_of($revisions, 'DifferentialRevision');

    if (!$revisions) {
      return;
    }

    $revisions = mpull($revisions, null, 'getPHID');

    $edge_query = id(new PhabricatorEdgeQuery())
      ->withSourcePHIDs(array_keys($revisions))
      ->withEdgeTypes(
        array(
          DifferentialRevisionHasCommitEdgeType::EDGECONST,
        ));
    $edge_query->execute();

    foreach ($revisions as $phid => $revision) {
      $commit_phids = $edge_query->getDestinationPHIDs(array($phid));
      $revision->attachCommitPHIDs($commit_phids);
    }
  }

  private function loadDiffIDs($conn_r, array $revisions) {
    assert_instances_of($revisions, 'DifferentialRevision');

    $diff_table = new DifferentialDiff();

    $diff_ids = queryfx_all(
      $conn_r,
      'SELECT revisionID, id FROM %T WHERE revisionID IN (%Ld)
        ORDER BY id DESC',
      $diff_table->getTableName(),
      mpull($revisions, 'getID'));
    $diff_ids = igroup($diff_ids, 'revisionID');

    foreach ($revisions as $revision) {
      $ids = idx($diff_ids, $revision->getID(), array());
      $ids = ipull($ids, 'id');
      $revision->attachDiffIDs($ids);
    }
  }

  private function loadActiveDiffs($conn_r, array $revisions) {
    assert_instances_of($revisions, 'DifferentialRevision');

    $diff_table = new DifferentialDiff();

    $load_ids = array();
    foreach ($revisions as $revision) {
      $diffs = $revision->getDiffIDs();
      if ($diffs) {
        $load_ids[] = max($diffs);
      }
    }

    $active_diffs = array();
    if ($load_ids) {
      $active_diffs = $diff_table->loadAllWhere(
        'id IN (%Ld)',
        $load_ids);
    }

    $active_diffs = mpull($active_diffs, null, 'getRevisionID');
    foreach ($revisions as $revision) {
      $revision->attachActiveDiff(idx($active_diffs, $revision->getID()));
    }
  }

  private function loadHashes(
    AphrontDatabaseConnection $conn_r,
    array $revisions) {
    assert_instances_of($revisions, 'DifferentialRevision');

    $data = queryfx_all(
      $conn_r,
      'SELECT * FROM %T WHERE revisionID IN (%Ld)',
      'differential_revisionhash',
      mpull($revisions, 'getID'));

    $data = igroup($data, 'revisionID');
    foreach ($revisions as $revision) {
      $hashes = idx($data, $revision->getID(), array());
      $list = array();
      foreach ($hashes as $hash) {
        $list[] = array($hash['type'], $hash['hash']);
      }
      $revision->attachHashes($list);
    }
  }

  private function loadReviewers(
    AphrontDatabaseConnection $conn,
    array $revisions) {

    assert_instances_of($revisions, 'DifferentialRevision');

    $reviewer_table = new DifferentialReviewer();
    $reviewer_rows = queryfx_all(
      $conn,
      'SELECT * FROM %T WHERE revisionPHID IN (%Ls)
        ORDER BY id ASC',
      $reviewer_table->getTableName(),
      mpull($revisions, 'getPHID'));
    $reviewer_list = $reviewer_table->loadAllFromArray($reviewer_rows);
    $reviewer_map = mgroup($reviewer_list, 'getRevisionPHID');

    foreach ($reviewer_map as $key => $reviewers) {
      $reviewer_map[$key] = mpull($reviewers, null, 'getReviewerPHID');
    }

    $viewer = $this->getViewer();
    $viewer_phid = $viewer->getPHID();

    $allow_key = 'differential.allow-self-accept';
    $allow_self = PhabricatorEnv::getEnvConfig($allow_key);

    // Figure out which of these reviewers the viewer has authority to act as.
    if ($this->needReviewerAuthority && $viewer_phid) {
      $authority = $this->loadReviewerAuthority(
        $revisions,
        $reviewer_map,
        $allow_self);
    }

    foreach ($revisions as $revision) {
      $reviewers = idx($reviewer_map, $revision->getPHID(), array());
      foreach ($reviewers as $reviewer_phid => $reviewer) {
        if ($this->needReviewerAuthority) {
          if (!$viewer_phid) {
            // Logged-out users never have authority.
            $has_authority = false;
          } else if ((!$allow_self) &&
                     ($revision->getAuthorPHID() == $viewer_phid)) {
            // The author can never have authority unless we allow self-accept.
            $has_authority = false;
          } else {
            // Otherwise, look up whether the viewer has authority.
            $has_authority = isset($authority[$reviewer_phid]);
          }

          $reviewer->attachAuthority($viewer, $has_authority);
        }

        $reviewers[$reviewer_phid] = $reviewer;
      }

      $revision->attachReviewers($reviewers);
    }
  }

  private function loadReviewerAuthority(
    array $revisions,
    array $reviewers,
    $allow_self) {

    $revision_map = mpull($revisions, null, 'getPHID');
    $viewer_phid = $this->getViewer()->getPHID();

    // Find all the project/package reviewers which the user may have authority
    // over.
    $project_phids = array();
    $package_phids = array();
    $project_type = PhabricatorProjectProjectPHIDType::TYPECONST;
    $package_type = PhabricatorOwnersPackagePHIDType::TYPECONST;

    foreach ($reviewers as $revision_phid => $reviewer_list) {
      if (!$allow_self) {
        if ($revision_map[$revision_phid]->getAuthorPHID() == $viewer_phid) {
          // If self-review isn't permitted, the user will never have
          // authority over projects on revisions they authored because you
          // can't accept your own revisions, so we don't need to load any
          // data about these reviewers.
          continue;
        }
      }

      foreach ($reviewer_list as $reviewer_phid => $reviewer) {
        $phid_type = phid_get_type($reviewer_phid);
        if ($phid_type == $project_type) {
          $project_phids[] = $reviewer_phid;
        }
        if ($phid_type == $package_type) {
          $package_phids[] = $reviewer_phid;
        }
      }
    }

    // The viewer has authority over themselves.
    $user_authority = array_fuse(array($viewer_phid));

    // And over any projects they are a member of.
    $project_authority = array();
    if ($project_phids) {
      $project_authority = id(new PhabricatorProjectQuery())
        ->setViewer($this->getViewer())
        ->withPHIDs($project_phids)
        ->withMemberPHIDs(array($viewer_phid))
        ->execute();
      $project_authority = mpull($project_authority, 'getPHID');
      $project_authority = array_fuse($project_authority);
    }

    // And over any packages they own.
    $package_authority = array();
    if ($package_phids) {
      $package_authority = id(new PhabricatorOwnersPackageQuery())
        ->setViewer($this->getViewer())
        ->withPHIDs($package_phids)
        ->withAuthorityPHIDs(array($viewer_phid))
        ->execute();
      $package_authority = mpull($package_authority, 'getPHID');
      $package_authority = array_fuse($package_authority);
    }

    return $user_authority + $project_authority + $package_authority;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorDifferentialApplication';
  }

  protected function getPrimaryTableAlias() {
    return 'r';
  }

}
