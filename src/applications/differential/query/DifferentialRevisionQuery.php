<?php

/**
 * Flexible query API for Differential revisions. Example:
 *
 *   // Load open revisions
 *   $revisions = id(new DifferentialRevisionQuery())
 *     ->withStatus(DifferentialRevisionQuery::STATUS_OPEN)
 *     ->execute();
 *
 * @task config   Query Configuration
 * @task exec     Query Execution
 * @task internal Internals
 */
final class DifferentialRevisionQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $pathIDs = array();

  private $status             = 'status-any';
  const STATUS_ANY            = 'status-any';
  const STATUS_OPEN           = 'status-open';
  const STATUS_ACCEPTED       = 'status-accepted';
  const STATUS_NEEDS_REVIEW   = 'status-needs-review';
  const STATUS_NEEDS_REVISION = 'status-needs-revision';
  const STATUS_CLOSED         = 'status-closed';
  const STATUS_ABANDONED      = 'status-abandoned';

  private $authors = array();
  private $draftAuthors = array();
  private $ccs = array();
  private $reviewers = array();
  private $revIDs = array();
  private $commitHashes = array();
  private $commitPHIDs = array();
  private $phids = array();
  private $responsibles = array();
  private $branches = array();
  private $repositoryPHIDs;
  private $updatedEpochMin;
  private $updatedEpochMax;

  const ORDER_MODIFIED      = 'order-modified';
  const ORDER_CREATED       = 'order-created';

  private $needRelationships  = false;
  private $needActiveDiffs    = false;
  private $needDiffIDs        = false;
  private $needCommitPHIDs    = false;
  private $needHashes         = false;
  private $needReviewerStatus = false;
  private $needReviewerAuthority;
  private $needDrafts;
  private $needFlags;

  private $buildingGlobalOrder;


/* -(  Query Configuration  )------------------------------------------------ */


  /**
   * Filter results to revisions which affect a Diffusion path ID in a given
   * repository. You can call this multiple times to select revisions for
   * several paths.
   *
   * @param int Diffusion repository ID.
   * @param int Diffusion path ID.
   * @return this
   * @task config
   */
  public function withPath($repository_id, $path_id) {
    $this->pathIDs[] = array(
      'repositoryID' => $repository_id,
      'pathID'       => $path_id,
    );
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
    $this->reviewers = $reviewer_phids;
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

  /**
   * Filter results to revisions that have one of the provided PHIDs as
   * commits. Calling this function will clear anything set by previous calls
   * to @{method:withCommitPHIDs}.
   *
   * @param array List of PHIDs of commits
   * @return this
   * @task config
   */
  public function withCommitPHIDs(array $commit_phids) {
    $this->commitPHIDs = $commit_phids;
    return $this;
  }

  /**
   * Filter results to revisions with a given status. Provide a class constant,
   * such as `DifferentialRevisionQuery::STATUS_OPEN`.
   *
   * @param const Class STATUS constant, like STATUS_OPEN.
   * @return this
   * @task config
   */
  public function withStatus($status_constant) {
    $this->status = $status_constant;
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



  /**
   * Set whether or not the query will load and attach relationships.
   *
   * @param bool True to load and attach relationships.
   * @return this
   * @task config
   */
  public function needRelationships($need_relationships) {
    $this->needRelationships = $need_relationships;
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
   * Set whether or not the query should load associated reviewer status.
   *
   * @param bool True to load and attach reviewers.
   * @return this
   * @task config
   */
  public function needReviewerStatus($need_reviewer_status) {
    $this->needReviewerStatus = $need_reviewer_status;
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

    if ($this->needRelationships) {
      $this->loadRelationships($conn_r, $revisions);
    }

    if ($this->needCommitPHIDs) {
      $this->loadCommitPHIDs($conn_r, $revisions);
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

    if ($this->needReviewerStatus || $this->needReviewerAuthority) {
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
      $viewer_phid = $viewer->getPHID();
      $draft_type = PhabricatorObjectHasDraftEdgeType::EDGECONST;

      if (!$viewer_phid) {
        // Viewers without a valid PHID can never have drafts.
        foreach ($revisions as $revision) {
          $revision->attachHasDraft($viewer, false);
        }
      } else {
        $edge_query = id(new PhabricatorEdgeQuery())
          ->withSourcePHIDs(mpull($revisions, 'getPHID'))
          ->withEdgeTypes(
            array(
              $draft_type,
            ))
          ->withDestinationPHIDs(array($viewer_phid));

        $edge_query->execute();

        foreach ($revisions as $revision) {
          $has_draft = (bool)$edge_query->getDestinationPHIDs(
            array(
              $revision->getPHID(),
            ));

          $revision->attachHasDraft($viewer, $has_draft);
        }
      }
    }

    return $revisions;
  }

  private function loadData() {
    $table = $this->newResultObject();
    $conn_r = $table->establishConnection('r');

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
        $selects[] = $this->buildSelectStatement($conn_r);

        // Build the query where the responsible users are reviewers, or
        // projects they are members of are reviewers.
        $this->authors = $basic_authors;
        $this->reviewers = array_merge($basic_reviewers, $this->responsibles);
        $selects[] = $this->buildSelectStatement($conn_r);

        // Put everything back like it was.
        $this->authors = $basic_authors;
        $this->reviewers = $basic_reviewers;
      } catch (Exception $ex) {
        $this->authors = $basic_authors;
        $this->reviewers = $basic_reviewers;
        throw $ex;
      }
    } else {
      $selects[] = $this->buildSelectStatement($conn_r);
    }

    if (count($selects) > 1) {
      $this->buildingGlobalOrder = true;
      $query = qsprintf(
        $conn_r,
        '%Q %Q %Q',
        implode(' UNION DISTINCT ', $selects),
        $this->buildOrderClause($conn_r),
        $this->buildLimitClause($conn_r));
    } else {
      $query = head($selects);
    }

    return queryfx_all($conn_r, '%Q', $query);
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

    $this->buildingGlobalOrder = false;
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
  private function buildJoinsClause($conn_r) {
    $joins = array();
    if ($this->pathIDs) {
      $path_table = new DifferentialAffectedPath();
      $joins[] = qsprintf(
        $conn_r,
        'JOIN %T p ON p.revisionID = r.id',
        $path_table->getTableName());
    }

    if ($this->commitHashes) {
      $joins[] = qsprintf(
        $conn_r,
        'JOIN %T hash_rel ON hash_rel.revisionID = r.id',
        ArcanistDifferentialRevisionHash::TABLE_NAME);
    }

    if ($this->ccs) {
      $joins[] = qsprintf(
        $conn_r,
        'JOIN %T e_ccs ON e_ccs.src = r.phid '.
        'AND e_ccs.type = %s '.
        'AND e_ccs.dst in (%Ls)',
        PhabricatorEdgeConfig::TABLE_NAME_EDGE,
        PhabricatorObjectHasSubscriberEdgeType::EDGECONST,
        $this->ccs);
    }

    if ($this->reviewers) {
      $joins[] = qsprintf(
        $conn_r,
        'JOIN %T e_reviewers ON e_reviewers.src = r.phid '.
        'AND e_reviewers.type = %s '.
        'AND e_reviewers.dst in (%Ls)',
        PhabricatorEdgeConfig::TABLE_NAME_EDGE,
        DifferentialRevisionHasReviewerEdgeType::EDGECONST,
        $this->reviewers);
    }

    if ($this->draftAuthors) {
      $joins[] = qsprintf(
        $conn_r,
        'JOIN %T has_draft ON has_draft.srcPHID = r.phid
          AND has_draft.type = %s
          AND has_draft.dstPHID IN (%Ls)',
        PhabricatorEdgeConfig::TABLE_NAME_EDGE,
        PhabricatorObjectHasDraftEdgeType::EDGECONST,
        $this->draftAuthors);
    }

    if ($this->commitPHIDs) {
      $joins[] = qsprintf(
        $conn_r,
        'JOIN %T commits ON commits.revisionID = r.id',
        DifferentialRevision::TABLE_COMMIT);
    }

    $joins[] = $this->buildJoinClauseParts($conn_r);

    return $this->formatJoinClause($joins);
  }


  /**
   * @task internal
   */
  protected function buildWhereClause(AphrontDatabaseConnection $conn_r) {
    $where = array();

    if ($this->pathIDs) {
      $path_clauses = array();
      $repo_info = igroup($this->pathIDs, 'repositoryID');
      foreach ($repo_info as $repository_id => $paths) {
        $path_clauses[] = qsprintf(
          $conn_r,
          '(p.repositoryID = %d AND p.pathID IN (%Ld))',
          $repository_id,
          ipull($paths, 'pathID'));
      }
      $path_clauses = '('.implode(' OR ', $path_clauses).')';
      $where[] = $path_clauses;
    }

    if ($this->authors) {
      $where[] = qsprintf(
        $conn_r,
        'r.authorPHID IN (%Ls)',
        $this->authors);
    }

    if ($this->revIDs) {
      $where[] = qsprintf(
        $conn_r,
        'r.id IN (%Ld)',
        $this->revIDs);
    }

    if ($this->repositoryPHIDs) {
      $where[] = qsprintf(
        $conn_r,
        'r.repositoryPHID IN (%Ls)',
        $this->repositoryPHIDs);
    }

    if ($this->commitHashes) {
      $hash_clauses = array();
      foreach ($this->commitHashes as $info) {
        list($type, $hash) = $info;
        $hash_clauses[] = qsprintf(
          $conn_r,
          '(hash_rel.type = %s AND hash_rel.hash = %s)',
          $type,
          $hash);
      }
      $hash_clauses = '('.implode(' OR ', $hash_clauses).')';
      $where[] = $hash_clauses;
    }

    if ($this->commitPHIDs) {
      $where[] = qsprintf(
        $conn_r,
        'commits.commitPHID IN (%Ls)',
        $this->commitPHIDs);
    }

    if ($this->phids) {
      $where[] = qsprintf(
        $conn_r,
        'r.phid IN (%Ls)',
        $this->phids);
    }

    if ($this->branches) {
      $where[] = qsprintf(
        $conn_r,
        'r.branchName in (%Ls)',
        $this->branches);
    }

    if ($this->updatedEpochMin !== null) {
      $where[] = qsprintf(
        $conn_r,
        'r.dateModified >= %d',
        $this->updatedEpochMin);
    }

    if ($this->updatedEpochMax !== null) {
      $where[] = qsprintf(
        $conn_r,
        'r.dateModified <= %d',
        $this->updatedEpochMax);
    }

    // NOTE: Although the status constants are integers in PHP, the column is a
    // string column in MySQL, and MySQL won't use keys on string columns if
    // you put integers in the query.

    switch ($this->status) {
      case self::STATUS_ANY:
        break;
      case self::STATUS_OPEN:
        $where[] = qsprintf(
          $conn_r,
          'r.status IN (%Ls)',
          DifferentialRevisionStatus::getOpenStatuses());
        break;
      case self::STATUS_NEEDS_REVIEW:
        $where[] = qsprintf(
          $conn_r,
          'r.status IN (%Ls)',
          array(
            ArcanistDifferentialRevisionStatus::NEEDS_REVIEW,
          ));
        break;
      case self::STATUS_NEEDS_REVISION:
        $where[] = qsprintf(
          $conn_r,
          'r.status IN (%Ls)',
          array(
            ArcanistDifferentialRevisionStatus::NEEDS_REVISION,
          ));
        break;
      case self::STATUS_ACCEPTED:
        $where[] = qsprintf(
          $conn_r,
          'r.status IN (%Ls)',
          array(
            ArcanistDifferentialRevisionStatus::ACCEPTED,
          ));
        break;
      case self::STATUS_CLOSED:
        $where[] = qsprintf(
          $conn_r,
          'r.status IN (%Ls)',
          DifferentialRevisionStatus::getClosedStatuses());
        break;
      case self::STATUS_ABANDONED:
        $where[] = qsprintf(
          $conn_r,
          'r.status IN (%Ls)',
          array(
            ArcanistDifferentialRevisionStatus::ABANDONED,
          ));
        break;
      default:
        throw new Exception(
          pht("Unknown revision status filter constant '%s'!", $this->status));
    }

    $where[] = $this->buildWhereClauseParts($conn_r);
    return $this->formatWhereClause($where);
  }


  /**
   * @task internal
   */
  protected function shouldGroupQueryResultRows() {

    $join_triggers = array_merge(
      $this->pathIDs,
      $this->ccs,
      $this->reviewers);

    if (count($join_triggers) > 1) {
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
    $primary = ($this->buildingGlobalOrder ? null : 'r');

    return array(
      'id' => array(
        'table' => $primary,
        'column' => 'id',
        'type' => 'int',
        'unique' => true,
      ),
      'updated' => array(
        'table' => $primary,
        'column' => 'dateModified',
        'type' => 'int',
      ),
    );
  }

  protected function getPagingValueMap($cursor, array $keys) {
    $revision = $this->loadCursorObject($cursor);
    return array(
      'id' => $revision->getID(),
      'updated' => $revision->getDateModified(),
    );
  }

  private function loadRelationships($conn_r, array $revisions) {
    assert_instances_of($revisions, 'DifferentialRevision');

    $type_reviewer = DifferentialRevisionHasReviewerEdgeType::EDGECONST;
    $type_subscriber = PhabricatorObjectHasSubscriberEdgeType::EDGECONST;

    $edges = id(new PhabricatorEdgeQuery())
      ->withSourcePHIDs(mpull($revisions, 'getPHID'))
      ->withEdgeTypes(array($type_reviewer, $type_subscriber))
      ->setOrder(PhabricatorEdgeQuery::ORDER_OLDEST_FIRST)
      ->execute();

    $type_map = array(
      DifferentialRevision::RELATION_REVIEWER => $type_reviewer,
      DifferentialRevision::RELATION_SUBSCRIBED => $type_subscriber,
    );

    foreach ($revisions as $revision) {
      $data = array();
      foreach ($type_map as $rel_type => $edge_type) {
        $revision_edges = $edges[$revision->getPHID()][$edge_type];
        foreach ($revision_edges as $dst_phid => $edge_data) {
          $data[] = array(
            'relation' => $rel_type,
            'objectPHID' => $dst_phid,
            'reasonPHID' => null,
          );
        }
      }

      $revision->attachRelationships($data);
    }
  }

  private function loadCommitPHIDs($conn_r, array $revisions) {
    assert_instances_of($revisions, 'DifferentialRevision');
    $commit_phids = queryfx_all(
      $conn_r,
      'SELECT * FROM %T WHERE revisionID IN (%Ld)',
      DifferentialRevision::TABLE_COMMIT,
      mpull($revisions, 'getID'));
    $commit_phids = igroup($commit_phids, 'revisionID');
    foreach ($revisions as $revision) {
      $phids = idx($commit_phids, $revision->getID(), array());
      $phids = ipull($phids, 'commitPHID');
      $revision->attachCommitPHIDs($phids);
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
    AphrontDatabaseConnection $conn_r,
    array $revisions) {

    assert_instances_of($revisions, 'DifferentialRevision');
    $edge_type = DifferentialRevisionHasReviewerEdgeType::EDGECONST;

    $edges = id(new PhabricatorEdgeQuery())
      ->withSourcePHIDs(mpull($revisions, 'getPHID'))
      ->withEdgeTypes(array($edge_type))
      ->needEdgeData(true)
      ->setOrder(PhabricatorEdgeQuery::ORDER_OLDEST_FIRST)
      ->execute();

    $viewer = $this->getViewer();
    $viewer_phid = $viewer->getPHID();
    $allow_key = 'differential.allow-self-accept';
    $allow_self = PhabricatorEnv::getEnvConfig($allow_key);

    // Figure out which of these reviewers the viewer has authority to act as.
    if ($this->needReviewerAuthority && $viewer_phid) {
      $authority = $this->loadReviewerAuthority(
        $revisions,
        $edges,
        $allow_self);
    }

    foreach ($revisions as $revision) {
      $revision_edges = $edges[$revision->getPHID()][$edge_type];
      $reviewers = array();
      foreach ($revision_edges as $reviewer_phid => $edge) {
        $reviewer = new DifferentialReviewerProxy(
          $reviewer_phid,
          $edge['data']);

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

      $revision->attachReviewerStatus($reviewers);
    }
  }

  private function loadReviewerAuthority(
    array $revisions,
    array $edges,
    $allow_self) {

    $revision_map = mpull($revisions, null, 'getPHID');
    $viewer_phid = $this->getViewer()->getPHID();

    // Find all the project/package reviewers which the user may have authority
    // over.
    $project_phids = array();
    $package_phids = array();
    $project_type = PhabricatorProjectProjectPHIDType::TYPECONST;
    $package_type = PhabricatorOwnersPackagePHIDType::TYPECONST;

    $edge_type = DifferentialRevisionHasReviewerEdgeType::EDGECONST;
    foreach ($edges as $src => $types) {
      if (!$allow_self) {
        if ($revision_map[$src]->getAuthorPHID() == $viewer_phid) {
          // If self-review isn't permitted, the user will never have
          // authority over projects on revisions they authored because you
          // can't accept your own revisions, so we don't need to load any
          // data about these reviewers.
          continue;
        }
      }
      $edge_data = idx($types, $edge_type, array());
      foreach ($edge_data as $dst => $data) {
        $phid_type = phid_get_type($dst);
        if ($phid_type == $project_type) {
          $project_phids[] = $dst;
        }
        if ($phid_type == $package_type) {
          $package_phids[] = $dst;
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
