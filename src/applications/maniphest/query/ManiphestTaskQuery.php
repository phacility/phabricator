<?php

/**
 * Query tasks by specific criteria. This class uses the higher-performance
 * but less-general Maniphest indexes to satisfy queries.
 */
final class ManiphestTaskQuery extends PhabricatorCursorPagedPolicyAwareQuery {

  private $taskIDs;
  private $taskPHIDs;
  private $authorPHIDs;
  private $ownerPHIDs;
  private $noOwner;
  private $anyOwner;
  private $subscriberPHIDs;
  private $dateCreatedAfter;
  private $dateCreatedBefore;
  private $dateModifiedAfter;
  private $dateModifiedBefore;
  private $bridgedObjectPHIDs;
  private $hasOpenParents;
  private $hasOpenSubtasks;
  private $parentTaskIDs;
  private $subtaskIDs;
  private $subtypes;
  private $closedEpochMin;
  private $closedEpochMax;
  private $closerPHIDs;
  private $columnPHIDs;
  private $specificGroupByProjectPHID;

  private $status           = 'status-any';
  const STATUS_ANY          = 'status-any';
  const STATUS_OPEN         = 'status-open';
  const STATUS_CLOSED       = 'status-closed';
  const STATUS_RESOLVED     = 'status-resolved';
  const STATUS_WONTFIX      = 'status-wontfix';
  const STATUS_INVALID      = 'status-invalid';
  const STATUS_SPITE        = 'status-spite';
  const STATUS_DUPLICATE    = 'status-duplicate';

  private $statuses;
  private $priorities;
  private $subpriorities;

  private $groupBy          = 'group-none';
  const GROUP_NONE          = 'group-none';
  const GROUP_PRIORITY      = 'group-priority';
  const GROUP_OWNER         = 'group-owner';
  const GROUP_STATUS        = 'group-status';
  const GROUP_PROJECT       = 'group-project';

  const ORDER_PRIORITY      = 'order-priority';
  const ORDER_CREATED       = 'order-created';
  const ORDER_MODIFIED      = 'order-modified';
  const ORDER_TITLE         = 'order-title';

  private $needSubscriberPHIDs;
  private $needProjectPHIDs;

  public function withAuthors(array $authors) {
    $this->authorPHIDs = $authors;
    return $this;
  }

  public function withIDs(array $ids) {
    $this->taskIDs = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->taskPHIDs = $phids;
    return $this;
  }

  public function withOwners(array $owners) {
    if ($owners === array()) {
      throw new Exception(pht('Empty withOwners() constraint is not valid.'));
    }

    $no_owner = PhabricatorPeopleNoOwnerDatasource::FUNCTION_TOKEN;
    $any_owner = PhabricatorPeopleAnyOwnerDatasource::FUNCTION_TOKEN;

    foreach ($owners as $k => $phid) {
      if ($phid === $no_owner || $phid === null) {
        $this->noOwner = true;
        unset($owners[$k]);
        break;
      }
      if ($phid === $any_owner) {
        $this->anyOwner = true;
        unset($owners[$k]);
        break;
      }
    }

    if ($owners) {
      $this->ownerPHIDs = $owners;
    }

    return $this;
  }

  public function withStatus($status) {
    $this->status = $status;
    return $this;
  }

  public function withStatuses(array $statuses) {
    $this->statuses = $statuses;
    return $this;
  }

  public function withPriorities(array $priorities) {
    $this->priorities = $priorities;
    return $this;
  }

  public function withSubpriorities(array $subpriorities) {
    $this->subpriorities = $subpriorities;
    return $this;
  }

  public function withSubscribers(array $subscribers) {
    $this->subscriberPHIDs = $subscribers;
    return $this;
  }

  public function setGroupBy($group) {
    $this->groupBy = $group;

    switch ($this->groupBy) {
      case self::GROUP_NONE:
        $vector = array();
        break;
      case self::GROUP_PRIORITY:
        $vector = array('priority');
        break;
      case self::GROUP_OWNER:
        $vector = array('owner');
        break;
      case self::GROUP_STATUS:
        $vector = array('status');
        break;
      case self::GROUP_PROJECT:
        $vector = array('project');
        break;
    }

    $this->setGroupVector($vector);

    return $this;
  }

  public function withOpenSubtasks($value) {
    $this->hasOpenSubtasks = $value;
    return $this;
  }

  public function withOpenParents($value) {
    $this->hasOpenParents = $value;
    return $this;
  }

  public function withParentTaskIDs(array $ids) {
    $this->parentTaskIDs = $ids;
    return $this;
  }

  public function withSubtaskIDs(array $ids) {
    $this->subtaskIDs = $ids;
    return $this;
  }

  public function withDateCreatedBefore($date_created_before) {
    $this->dateCreatedBefore = $date_created_before;
    return $this;
  }

  public function withDateCreatedAfter($date_created_after) {
    $this->dateCreatedAfter = $date_created_after;
    return $this;
  }

  public function withDateModifiedBefore($date_modified_before) {
    $this->dateModifiedBefore = $date_modified_before;
    return $this;
  }

  public function withDateModifiedAfter($date_modified_after) {
    $this->dateModifiedAfter = $date_modified_after;
    return $this;
  }

  public function withClosedEpochBetween($min, $max) {
    $this->closedEpochMin = $min;
    $this->closedEpochMax = $max;
    return $this;
  }

  public function withCloserPHIDs(array $phids) {
    $this->closerPHIDs = $phids;
    return $this;
  }

  public function needSubscriberPHIDs($bool) {
    $this->needSubscriberPHIDs = $bool;
    return $this;
  }

  public function needProjectPHIDs($bool) {
    $this->needProjectPHIDs = $bool;
    return $this;
  }

  public function withBridgedObjectPHIDs(array $phids) {
    $this->bridgedObjectPHIDs = $phids;
    return $this;
  }

  public function withSubtypes(array $subtypes) {
    $this->subtypes = $subtypes;
    return $this;
  }

  public function withColumnPHIDs(array $column_phids) {
    $this->columnPHIDs = $column_phids;
    return $this;
  }

  public function withSpecificGroupByProjectPHID($project_phid) {
    $this->specificGroupByProjectPHID = $project_phid;
    return $this;
  }

  public function newResultObject() {
    return new ManiphestTask();
  }

  protected function loadPage() {
    $task_dao = new ManiphestTask();
    $conn = $task_dao->establishConnection('r');

    $where = $this->buildWhereClause($conn);

    $group_column = qsprintf($conn, '');
    switch ($this->groupBy) {
      case self::GROUP_PROJECT:
        $group_column = qsprintf(
          $conn,
          ', projectGroupName.indexedObjectPHID projectGroupPHID');
        break;
    }

    $rows = queryfx_all(
      $conn,
      '%Q %Q FROM %T task %Q %Q %Q %Q %Q %Q',
      $this->buildSelectClause($conn),
      $group_column,
      $task_dao->getTableName(),
      $this->buildJoinClause($conn),
      $where,
      $this->buildGroupClause($conn),
      $this->buildHavingClause($conn),
      $this->buildOrderClause($conn),
      $this->buildLimitClause($conn));

    switch ($this->groupBy) {
      case self::GROUP_PROJECT:
        $data = ipull($rows, null, 'id');
        break;
      default:
        $data = $rows;
        break;
    }

    $data = $this->didLoadRawRows($data);
    $tasks = $task_dao->loadAllFromArray($data);

    switch ($this->groupBy) {
      case self::GROUP_PROJECT:
        $results = array();
        foreach ($rows as $row) {
          $task = clone $tasks[$row['id']];
          $task->attachGroupByProjectPHID($row['projectGroupPHID']);
          $results[] = $task;
        }
        $tasks = $results;
        break;
    }

    return $tasks;
  }

  protected function willFilterPage(array $tasks) {
    if ($this->groupBy == self::GROUP_PROJECT) {
      // We should only return project groups which the user can actually see.
      $project_phids = mpull($tasks, 'getGroupByProjectPHID');
      $projects = id(new PhabricatorProjectQuery())
        ->setViewer($this->getViewer())
        ->withPHIDs($project_phids)
        ->execute();
      $projects = mpull($projects, null, 'getPHID');

      foreach ($tasks as $key => $task) {
        if (!$task->getGroupByProjectPHID()) {
          // This task is either not tagged with any projects, or only tagged
          // with projects which we're ignoring because they're being queried
          // for explicitly.
          continue;
        }

        if (empty($projects[$task->getGroupByProjectPHID()])) {
          unset($tasks[$key]);
        }
      }
    }

    return $tasks;
  }

  protected function didFilterPage(array $tasks) {
    $phids = mpull($tasks, 'getPHID');

    if ($this->needProjectPHIDs) {
      $edge_query = id(new PhabricatorEdgeQuery())
        ->withSourcePHIDs($phids)
        ->withEdgeTypes(
          array(
            PhabricatorProjectObjectHasProjectEdgeType::EDGECONST,
          ));
      $edge_query->execute();

      foreach ($tasks as $task) {
        $project_phids = $edge_query->getDestinationPHIDs(
          array($task->getPHID()));
        $task->attachProjectPHIDs($project_phids);
      }
    }

    if ($this->needSubscriberPHIDs) {
      $subscriber_sets = id(new PhabricatorSubscribersQuery())
        ->withObjectPHIDs($phids)
        ->execute();
      foreach ($tasks as $task) {
        $subscribers = idx($subscriber_sets, $task->getPHID(), array());
        $task->attachSubscriberPHIDs($subscribers);
      }
    }

    return $tasks;
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    $where[] = $this->buildStatusWhereClause($conn);
    $where[] = $this->buildOwnerWhereClause($conn);

    if ($this->taskIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'task.id in (%Ld)',
        $this->taskIDs);
    }

    if ($this->taskPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'task.phid in (%Ls)',
        $this->taskPHIDs);
    }

    if ($this->statuses !== null) {
      $where[] = qsprintf(
        $conn,
        'task.status IN (%Ls)',
        $this->statuses);
    }

    if ($this->authorPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'task.authorPHID in (%Ls)',
        $this->authorPHIDs);
    }

    if ($this->dateCreatedAfter) {
      $where[] = qsprintf(
        $conn,
        'task.dateCreated >= %d',
        $this->dateCreatedAfter);
    }

    if ($this->dateCreatedBefore) {
      $where[] = qsprintf(
        $conn,
        'task.dateCreated <= %d',
        $this->dateCreatedBefore);
    }

    if ($this->dateModifiedAfter) {
      $where[] = qsprintf(
        $conn,
        'task.dateModified >= %d',
        $this->dateModifiedAfter);
    }

    if ($this->dateModifiedBefore) {
      $where[] = qsprintf(
        $conn,
        'task.dateModified <= %d',
        $this->dateModifiedBefore);
    }

    if ($this->closedEpochMin !== null) {
      $where[] = qsprintf(
        $conn,
        'task.closedEpoch >= %d',
        $this->closedEpochMin);
    }

    if ($this->closedEpochMax !== null) {
      $where[] = qsprintf(
        $conn,
        'task.closedEpoch <= %d',
        $this->closedEpochMax);
    }

    if ($this->closerPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'task.closerPHID IN (%Ls)',
        $this->closerPHIDs);
    }

    if ($this->priorities !== null) {
      $where[] = qsprintf(
        $conn,
        'task.priority IN (%Ld)',
        $this->priorities);
    }

    if ($this->bridgedObjectPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'task.bridgedObjectPHID IN (%Ls)',
        $this->bridgedObjectPHIDs);
    }

    if ($this->subtypes !== null) {
      $where[] = qsprintf(
        $conn,
        'task.subtype IN (%Ls)',
        $this->subtypes);
    }


    if ($this->columnPHIDs !== null) {
      $viewer = $this->getViewer();

      $columns = id(new PhabricatorProjectColumnQuery())
        ->setParentQuery($this)
        ->setViewer($viewer)
        ->withPHIDs($this->columnPHIDs)
        ->execute();
      if (!$columns) {
        throw new PhabricatorEmptyQueryException();
      }

      // We must do board layout before we move forward because the column
      // positions may not yet exist otherwise. An example is that newly
      // created tasks may not yet be positioned in the backlog column.

      $projects = mpull($columns, 'getProject');
      $projects = mpull($projects, null, 'getPHID');

      // The board layout engine needs to know about every object that it's
      // going to be asked to do layout for. For now, we're just doing layout
      // on every object on the boards. In the future, we could do layout on a
      // smaller set of objects by using the constraints on this Query. For
      // example, if the caller is only asking for open tasks, we only need
      // to do layout on open tasks.

      // This fetches too many objects (every type of object tagged with the
      // project, not just tasks). We could narrow it by querying the edge
      // table on the Maniphest side, but there's currently no way to build
      // that query with EdgeQuery.
      $edge_query = id(new PhabricatorEdgeQuery())
        ->withSourcePHIDs(array_keys($projects))
        ->withEdgeTypes(
          array(
            PhabricatorProjectProjectHasObjectEdgeType::EDGECONST,
          ));

      $edge_query->execute();
      $all_phids = $edge_query->getDestinationPHIDs();

      // Since we overfetched PHIDs, filter out any non-tasks we got back.
      foreach ($all_phids as $key => $phid) {
        if (phid_get_type($phid) !== ManiphestTaskPHIDType::TYPECONST) {
          unset($all_phids[$key]);
        }
      }

      // If there are no tasks on the relevant boards, this query can't
      // possibly hit anything so we're all done.
      $task_phids = array_fuse($all_phids);
      if (!$task_phids) {
        throw new PhabricatorEmptyQueryException();
      }

      // We know everything we need to know, so perform board layout.
      $engine = id(new PhabricatorBoardLayoutEngine())
        ->setViewer($viewer)
        ->setFetchAllBoards(true)
        ->setBoardPHIDs(array_keys($projects))
        ->setObjectPHIDs($task_phids)
        ->executeLayout();

      // Find the tasks that are in the constraint columns after board layout
      // completes.
      $select_phids = array();
      foreach ($columns as $column) {
        $in_column = $engine->getColumnObjectPHIDs(
          $column->getProjectPHID(),
          $column->getPHID());
        foreach ($in_column as $phid) {
          $select_phids[$phid] = $phid;
        }
      }

      if (!$select_phids) {
        throw new PhabricatorEmptyQueryException();
      }

      $where[] = qsprintf(
        $conn,
        'task.phid IN (%Ls)',
        $select_phids);
    }

    if ($this->specificGroupByProjectPHID !== null) {
      $where[] = qsprintf(
        $conn,
        'projectGroupName.indexedObjectPHID = %s',
        $this->specificGroupByProjectPHID);
      }

    return $where;
  }

  private function buildStatusWhereClause(AphrontDatabaseConnection $conn) {
    static $map = array(
      self::STATUS_RESOLVED   => ManiphestTaskStatus::STATUS_CLOSED_RESOLVED,
      self::STATUS_WONTFIX    => ManiphestTaskStatus::STATUS_CLOSED_WONTFIX,
      self::STATUS_INVALID    => ManiphestTaskStatus::STATUS_CLOSED_INVALID,
      self::STATUS_SPITE      => ManiphestTaskStatus::STATUS_CLOSED_SPITE,
      self::STATUS_DUPLICATE  => ManiphestTaskStatus::STATUS_CLOSED_DUPLICATE,
    );

    switch ($this->status) {
      case self::STATUS_ANY:
        return null;
      case self::STATUS_OPEN:
        return qsprintf(
          $conn,
          'task.status IN (%Ls)',
          ManiphestTaskStatus::getOpenStatusConstants());
      case self::STATUS_CLOSED:
        return qsprintf(
          $conn,
          'task.status IN (%Ls)',
          ManiphestTaskStatus::getClosedStatusConstants());
      default:
        $constant = idx($map, $this->status);
        if (!$constant) {
          throw new Exception(pht("Unknown status query '%s'!", $this->status));
        }
        return qsprintf(
          $conn,
          'task.status = %s',
          $constant);
    }
  }

  private function buildOwnerWhereClause(AphrontDatabaseConnection $conn) {
    $subclause = array();

    if ($this->noOwner) {
      $subclause[] = qsprintf(
        $conn,
        'task.ownerPHID IS NULL');
    }

    if ($this->anyOwner) {
      $subclause[] = qsprintf(
        $conn,
        'task.ownerPHID IS NOT NULL');
    }

    if ($this->ownerPHIDs !== null) {
      $subclause[] = qsprintf(
        $conn,
        'task.ownerPHID IN (%Ls)',
        $this->ownerPHIDs);
    }

    if (!$subclause) {
      return qsprintf($conn, '');
    }

    return qsprintf($conn, '%LO', $subclause);
  }

  protected function buildJoinClauseParts(AphrontDatabaseConnection $conn) {
    $open_statuses = ManiphestTaskStatus::getOpenStatusConstants();
    $edge_table = PhabricatorEdgeConfig::TABLE_NAME_EDGE;
    $task_table = $this->newResultObject()->getTableName();

    $parent_type = ManiphestTaskDependedOnByTaskEdgeType::EDGECONST;
    $subtask_type = ManiphestTaskDependsOnTaskEdgeType::EDGECONST;

    $joins = array();
    if ($this->hasOpenParents !== null) {
      if ($this->hasOpenParents) {
        $join_type = qsprintf($conn, 'JOIN');
      } else {
        $join_type = qsprintf($conn, 'LEFT JOIN');
      }

      $joins[] = qsprintf(
        $conn,
        '%Q %T e_parent
          ON e_parent.src = task.phid
          AND e_parent.type = %d
         %Q %T parent
           ON e_parent.dst = parent.phid
           AND parent.status IN (%Ls)',
        $join_type,
        $edge_table,
        $parent_type,
        $join_type,
        $task_table,
        $open_statuses);
    }

    if ($this->hasOpenSubtasks !== null) {
      if ($this->hasOpenSubtasks) {
        $join_type = qsprintf($conn, 'JOIN');
      } else {
        $join_type = qsprintf($conn, 'LEFT JOIN');
      }

      $joins[] = qsprintf(
        $conn,
        '%Q %T e_subtask
          ON e_subtask.src = task.phid
          AND e_subtask.type = %d
         %Q %T subtask
           ON e_subtask.dst = subtask.phid
           AND subtask.status IN (%Ls)',
        $join_type,
        $edge_table,
        $subtask_type,
        $join_type,
        $task_table,
        $open_statuses);
    }

    if ($this->subscriberPHIDs !== null) {
      $joins[] = qsprintf(
        $conn,
        'JOIN %T e_ccs ON e_ccs.src = task.phid '.
        'AND e_ccs.type = %s '.
        'AND e_ccs.dst in (%Ls)',
        PhabricatorEdgeConfig::TABLE_NAME_EDGE,
        PhabricatorObjectHasSubscriberEdgeType::EDGECONST,
        $this->subscriberPHIDs);
    }

    switch ($this->groupBy) {
      case self::GROUP_PROJECT:
        $ignore_group_phids = $this->getIgnoreGroupedProjectPHIDs();
        if ($ignore_group_phids) {
          $joins[] = qsprintf(
            $conn,
            'LEFT JOIN %T projectGroup ON task.phid = projectGroup.src
              AND projectGroup.type = %d
              AND projectGroup.dst NOT IN (%Ls)',
            $edge_table,
            PhabricatorProjectObjectHasProjectEdgeType::EDGECONST,
            $ignore_group_phids);
        } else {
          $joins[] = qsprintf(
            $conn,
            'LEFT JOIN %T projectGroup ON task.phid = projectGroup.src
              AND projectGroup.type = %d',
            $edge_table,
            PhabricatorProjectObjectHasProjectEdgeType::EDGECONST);
        }
        $joins[] = qsprintf(
          $conn,
          'LEFT JOIN %T projectGroupName
            ON projectGroup.dst = projectGroupName.indexedObjectPHID',
          id(new ManiphestNameIndex())->getTableName());
        break;
    }

    if ($this->parentTaskIDs !== null) {
      $joins[] = qsprintf(
        $conn,
        'JOIN %T e_has_parent
          ON e_has_parent.src = task.phid
          AND e_has_parent.type = %d
         JOIN %T has_parent
           ON e_has_parent.dst = has_parent.phid
           AND has_parent.id IN (%Ld)',
        $edge_table,
        $parent_type,
        $task_table,
        $this->parentTaskIDs);
    }

    if ($this->subtaskIDs !== null) {
      $joins[] = qsprintf(
        $conn,
        'JOIN %T e_has_subtask
          ON e_has_subtask.src = task.phid
          AND e_has_subtask.type = %d
         JOIN %T has_subtask
           ON e_has_subtask.dst = has_subtask.phid
           AND has_subtask.id IN (%Ld)',
        $edge_table,
        $subtask_type,
        $task_table,
        $this->subtaskIDs);
    }

    $joins[] = parent::buildJoinClauseParts($conn);

    return $joins;
  }

  protected function buildGroupClause(AphrontDatabaseConnection $conn) {
    $joined_multiple_rows =
      ($this->hasOpenParents !== null) ||
      ($this->hasOpenSubtasks !== null) ||
      ($this->parentTaskIDs !== null) ||
      ($this->subtaskIDs !== null) ||
      $this->shouldGroupQueryResultRows();

    $joined_project_name = ($this->groupBy == self::GROUP_PROJECT);

    // If we're joining multiple rows, we need to group the results by the
    // task IDs.
    if ($joined_multiple_rows) {
      if ($joined_project_name) {
        return qsprintf($conn, 'GROUP BY task.phid, projectGroup.dst');
      } else {
        return qsprintf($conn, 'GROUP BY task.phid');
      }
    }

    return qsprintf($conn, '');
  }


  protected function buildHavingClauseParts(AphrontDatabaseConnection $conn) {
    $having = parent::buildHavingClauseParts($conn);

    if ($this->hasOpenParents !== null) {
      if (!$this->hasOpenParents) {
        $having[] = qsprintf(
          $conn,
          'COUNT(parent.phid) = 0');
      }
    }

    if ($this->hasOpenSubtasks !== null) {
      if (!$this->hasOpenSubtasks) {
        $having[] = qsprintf(
          $conn,
          'COUNT(subtask.phid) = 0');
      }
    }

    return $having;
  }


  /**
   * Return project PHIDs which we should ignore when grouping tasks by
   * project. For example, if a user issues a query like:
   *
   *   Tasks tagged with all projects: Frontend, Bugs
   *
   * ...then we don't show "Frontend" or "Bugs" groups in the result set, since
   * they're meaningless as all results are in both groups.
   *
   * Similarly, for queries like:
   *
   *   Tasks tagged with any projects: Public Relations
   *
   * ...we ignore the single project, as every result is in that project. (In
   * the case that there are several "any" projects, we do not ignore them.)
   *
   * @return list<phid> Project PHIDs which should be ignored in query
   *                    construction.
   */
  private function getIgnoreGroupedProjectPHIDs() {
    // Maybe we should also exclude the "OPERATOR_NOT" PHIDs? It won't
    // impact the results, but we might end up with a better query plan.
    // Investigate this on real data? This is likely very rare.

    $edge_types = array(
      PhabricatorProjectObjectHasProjectEdgeType::EDGECONST,
    );

    $phids = array();

    $phids[] = $this->getEdgeLogicValues(
      $edge_types,
      array(
        PhabricatorQueryConstraint::OPERATOR_AND,
      ));

    $any = $this->getEdgeLogicValues(
      $edge_types,
      array(
        PhabricatorQueryConstraint::OPERATOR_OR,
      ));
    if (count($any) == 1) {
      $phids[] = $any;
    }

    return array_mergev($phids);
  }

  public function getBuiltinOrders() {
    $orders = array(
      'priority' => array(
        'vector' => array('priority', 'id'),
        'name' => pht('Priority'),
        'aliases' => array(self::ORDER_PRIORITY),
      ),
      'updated' => array(
        'vector' => array('updated', 'id'),
        'name' => pht('Date Updated (Latest First)'),
        'aliases' => array(self::ORDER_MODIFIED),
      ),
      'outdated' => array(
        'vector' => array('-updated', '-id'),
        'name' => pht('Date Updated (Oldest First)'),
      ),
      'closed' => array(
        'vector' => array('closed', 'id'),
        'name' => pht('Date Closed (Latest First)'),
      ),
      'title' => array(
        'vector' => array('title', 'id'),
        'name' => pht('Title'),
        'aliases' => array(self::ORDER_TITLE),
      ),
    ) + parent::getBuiltinOrders();

    // Alias the "newest" builtin to the historical key for it.
    $orders['newest']['aliases'][] = self::ORDER_CREATED;

    $orders = array_select_keys(
      $orders,
      array(
        'priority',
        'updated',
        'outdated',
        'newest',
        'oldest',
        'closed',
        'title',
      )) + $orders;

    return $orders;
  }

  public function getOrderableColumns() {
    return parent::getOrderableColumns() + array(
      'priority' => array(
        'table' => 'task',
        'column' => 'priority',
        'type' => 'int',
      ),
      'owner' => array(
        'table' => 'task',
        'column' => 'ownerOrdering',
        'null' => 'head',
        'reverse' => true,
        'type' => 'string',
      ),
      'status' => array(
        'table' => 'task',
        'column' => 'status',
        'type' => 'string',
        'reverse' => true,
      ),
      'project' => array(
        'table' => 'projectGroupName',
        'column' => 'indexedObjectName',
        'type' => 'string',
        'null' => 'head',
        'reverse' => true,
      ),
      'title' => array(
        'table' => 'task',
        'column' => 'title',
        'type' => 'string',
        'reverse' => true,
      ),
      'updated' => array(
        'table' => 'task',
        'column' => 'dateModified',
        'type' => 'int',
      ),
      'closed' => array(
        'table' => 'task',
        'column' => 'closedEpoch',
        'type' => 'int',
        'null' => 'tail',
      ),
    );
  }

  protected function newPagingMapFromCursorObject(
    PhabricatorQueryCursor $cursor,
    array $keys) {

    $task = $cursor->getObject();

    $map = array(
      'id' => (int)$task->getID(),
      'priority' => (int)$task->getPriority(),
      'owner' => $task->getOwnerOrdering(),
      'status' => $task->getStatus(),
      'title' => $task->getTitle(),
      'updated' => (int)$task->getDateModified(),
      'closed' => $task->getClosedEpoch(),
    );

    if (isset($keys['project'])) {
      $value = null;

      $group_phid = $task->getGroupByProjectPHID();
      if ($group_phid) {
        $paging_projects = id(new PhabricatorProjectQuery())
          ->setViewer($this->getViewer())
          ->withPHIDs(array($group_phid))
          ->execute();
        if ($paging_projects) {
          $value = head($paging_projects)->getName();
        }
      }

      $map['project'] = $value;
    }

    foreach ($keys as $key) {
      if ($this->isCustomFieldOrderKey($key)) {
        $map += $this->getPagingValueMapForCustomFields($task);
        break;
      }
    }

    return $map;
  }

  protected function newExternalCursorStringForResult($object) {
    $id = $object->getID();

    if ($this->groupBy == self::GROUP_PROJECT) {
      return rtrim($id.'.'.$object->getGroupByProjectPHID(), '.');
    }

    return $id;
  }

  protected function newInternalCursorFromExternalCursor($cursor) {
    list($task_id, $group_phid) = $this->parseCursor($cursor);

    $cursor_object = parent::newInternalCursorFromExternalCursor($cursor);

    if ($group_phid !== null) {
      $project = id(new PhabricatorProjectQuery())
        ->setViewer($this->getViewer())
        ->withPHIDs(array($group_phid))
        ->execute();

      if (!$project) {
        $this->throwCursorException(
          pht(
            'Group PHID ("%s") component of cursor ("%s") is not valid.',
            $group_phid,
            $cursor));
      }

      $cursor_object->getObject()->attachGroupByProjectPHID($group_phid);
    }

    return $cursor_object;
  }

  protected function applyExternalCursorConstraintsToQuery(
    PhabricatorCursorPagedPolicyAwareQuery $subquery,
    $cursor) {
    list($task_id, $group_phid) = $this->parseCursor($cursor);

    $subquery->withIDs(array($task_id));

    if ($group_phid) {
      $subquery->setGroupBy(self::GROUP_PROJECT);

      // The subquery needs to return exactly one result. If a task is in
      // several projects, the query may naturally return several results.
      // Specify that we want only the particular instance of the task in
      // the specified project.
      $subquery->withSpecificGroupByProjectPHID($group_phid);
    }
  }


  private function parseCursor($cursor) {
    // Split a "123.PHID-PROJ-abcd" cursor into a "Task ID" part and a
    // "Project PHID" part.

    $parts = explode('.', $cursor, 2);

    if (count($parts) < 2) {
      $parts[] = null;
    }

    if (!strlen($parts[1])) {
      $parts[1] = null;
    }

    return $parts;
  }

  protected function getPrimaryTableAlias() {
    return 'task';
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorManiphestApplication';
  }

}
