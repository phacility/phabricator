<?php

/**
 * Query tasks by specific criteria. This class uses the higher-performance
 * but less-general Maniphest indexes to satisfy queries.
 */
final class ManiphestTaskQuery extends PhabricatorCursorPagedPolicyAwareQuery {

  private $taskIDs             = array();
  private $taskPHIDs           = array();
  private $authorPHIDs         = array();
  private $ownerPHIDs          = array();
  private $noOwner;
  private $anyOwner;
  private $subscriberPHIDs     = array();
  private $dateCreatedAfter;
  private $dateCreatedBefore;
  private $dateModifiedAfter;
  private $dateModifiedBefore;
  private $subpriorityMin;
  private $subpriorityMax;

  private $fullTextSearch   = '';

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
  private $blockingTasks;
  private $blockedTasks;

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
    $this->ownerPHIDs = $owners;
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

  public function withSubpriorityBetween($min, $max) {
    $this->subpriorityMin = $min;
    $this->subpriorityMax = $max;
    return $this;
  }

  public function withSubscribers(array $subscribers) {
    $this->subscriberPHIDs = $subscribers;
    return $this;
  }

  public function withFullTextSearch($fulltext_search) {
    $this->fullTextSearch = $fulltext_search;
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

  /**
   * True returns tasks that are blocking other tasks only.
   * False returns tasks that are not blocking other tasks only.
   * Null returns tasks regardless of blocking status.
   */
  public function withBlockingTasks($mode) {
    $this->blockingTasks = $mode;
    return $this;
  }

  public function shouldJoinBlockingTasks() {
    return $this->blockingTasks !== null;
  }

  /**
   * True returns tasks that are blocked by other tasks only.
   * False returns tasks that are not blocked by other tasks only.
   * Null returns tasks regardless of blocked by status.
   */
  public function withBlockedTasks($mode) {
    $this->blockedTasks = $mode;
    return $this;
  }

  public function shouldJoinBlockedTasks() {
    return $this->blockedTasks !== null;
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

  public function needSubscriberPHIDs($bool) {
    $this->needSubscriberPHIDs = $bool;
    return $this;
  }

  public function needProjectPHIDs($bool) {
    $this->needProjectPHIDs = $bool;
    return $this;
  }

  public function newResultObject() {
    return new ManiphestTask();
  }

  protected function loadPage() {
    $task_dao = new ManiphestTask();
    $conn = $task_dao->establishConnection('r');

    $where = array();
    $where[] = $this->buildTaskIDsWhereClause($conn);
    $where[] = $this->buildTaskPHIDsWhereClause($conn);
    $where[] = $this->buildStatusWhereClause($conn);
    $where[] = $this->buildStatusesWhereClause($conn);
    $where[] = $this->buildDependenciesWhereClause($conn);
    $where[] = $this->buildAuthorWhereClause($conn);
    $where[] = $this->buildOwnerWhereClause($conn);
    $where[] = $this->buildFullTextWhereClause($conn);

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

    if ($this->priorities) {
      $where[] = qsprintf(
        $conn,
        'task.priority IN (%Ld)',
        $this->priorities);
    }

    if ($this->subpriorities) {
      $where[] = qsprintf(
        $conn,
        'task.subpriority IN (%Lf)',
        $this->subpriorities);
    }

    if ($this->subpriorityMin) {
      $where[] = qsprintf(
        $conn,
        'task.subpriority >= %f',
        $this->subpriorityMin);
    }

    if ($this->subpriorityMax) {
      $where[] = qsprintf(
        $conn,
        'task.subpriority <= %f',
        $this->subpriorityMax);
    }

    $where[] = $this->buildWhereClauseParts($conn);

    $where = $this->formatWhereClause($where);

    $group_column = '';
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
          // This task is either not in any projects, or only in projects
          // which we're ignoring because they're being queried for explicitly.
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

  private function buildTaskIDsWhereClause(AphrontDatabaseConnection $conn) {
    if (!$this->taskIDs) {
      return null;
    }

    return qsprintf(
      $conn,
      'task.id in (%Ld)',
      $this->taskIDs);
  }

  private function buildTaskPHIDsWhereClause(AphrontDatabaseConnection $conn) {
    if (!$this->taskPHIDs) {
      return null;
    }

    return qsprintf(
      $conn,
      'task.phid in (%Ls)',
      $this->taskPHIDs);
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

  private function buildStatusesWhereClause(AphrontDatabaseConnection $conn) {
    if ($this->statuses) {
      return qsprintf(
        $conn,
        'task.status IN (%Ls)',
        $this->statuses);
    }
    return null;
  }

  private function buildAuthorWhereClause(AphrontDatabaseConnection $conn) {
    if (!$this->authorPHIDs) {
      return null;
    }

    return qsprintf(
      $conn,
      'task.authorPHID in (%Ls)',
      $this->authorPHIDs);
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

    if ($this->ownerPHIDs) {
      $subclause[] = qsprintf(
        $conn,
        'task.ownerPHID IN (%Ls)',
        $this->ownerPHIDs);
    }

    if (!$subclause) {
      return '';
    }

    return '('.implode(') OR (', $subclause).')';
  }

  private function buildFullTextWhereClause(AphrontDatabaseConnection $conn) {
    if (!strlen($this->fullTextSearch)) {
      return null;
    }

    // In doing a fulltext search, we first find all the PHIDs that match the
    // fulltext search, and then use that to limit the rest of the search
    $fulltext_query = id(new PhabricatorSavedQuery())
      ->setEngineClassName('PhabricatorSearchApplicationSearchEngine')
      ->setParameter('query', $this->fullTextSearch);

    // NOTE: Setting this to something larger than 2^53 will raise errors in
    // ElasticSearch, and billions of results won't fit in memory anyway.
    $fulltext_query->setParameter('limit', 100000);
    $fulltext_query->setParameter('types',
      array(ManiphestTaskPHIDType::TYPECONST));

    $engine = PhabricatorSearchEngine::loadEngine();
    $fulltext_results = $engine->executeSearch($fulltext_query);

    if (empty($fulltext_results)) {
      $fulltext_results = array(null);
    }

    return qsprintf(
      $conn,
      'task.phid IN (%Ls)',
      $fulltext_results);
  }

  private function buildDependenciesWhereClause(
    AphrontDatabaseConnection $conn) {

    if (!$this->shouldJoinBlockedTasks() &&
        !$this->shouldJoinBlockingTasks()) {
      return null;
    }

    $parts = array();
    if ($this->blockingTasks === true) {
      $parts[] = qsprintf(
        $conn,
        'blocking.dst IS NOT NULL AND blockingtask.status IN (%Ls)',
        ManiphestTaskStatus::getOpenStatusConstants());
    } else if ($this->blockingTasks === false) {
      $parts[] = qsprintf(
        $conn,
        'blocking.dst IS NULL OR blockingtask.status NOT IN (%Ls)',
        ManiphestTaskStatus::getOpenStatusConstants());
    }

    if ($this->blockedTasks === true) {
      $parts[] = qsprintf(
        $conn,
        'blocked.dst IS NOT NULL AND blockedtask.status IN (%Ls)',
        ManiphestTaskStatus::getOpenStatusConstants());
    } else if ($this->blockedTasks === false) {
      $parts[] = qsprintf(
        $conn,
        'blocked.dst IS NULL OR blockedtask.status NOT IN (%Ls)',
        ManiphestTaskStatus::getOpenStatusConstants());
    }

    return '('.implode(') OR (', $parts).')';
  }

  protected function buildJoinClauseParts(AphrontDatabaseConnection $conn_r) {
    $edge_table = PhabricatorEdgeConfig::TABLE_NAME_EDGE;

    $joins = array();

    if ($this->shouldJoinBlockingTasks()) {
      $joins[] = qsprintf(
        $conn_r,
        'LEFT JOIN %T blocking ON blocking.src = task.phid '.
        'AND blocking.type = %d '.
        'LEFT JOIN %T blockingtask ON blocking.dst = blockingtask.phid',
        $edge_table,
        ManiphestTaskDependedOnByTaskEdgeType::EDGECONST,
        id(new ManiphestTask())->getTableName());
    }
    if ($this->shouldJoinBlockedTasks()) {
      $joins[] = qsprintf(
        $conn_r,
        'LEFT JOIN %T blocked ON blocked.src = task.phid '.
        'AND blocked.type = %d '.
        'LEFT JOIN %T blockedtask ON blocked.dst = blockedtask.phid',
        $edge_table,
        ManiphestTaskDependsOnTaskEdgeType::EDGECONST,
        id(new ManiphestTask())->getTableName());
    }

    if ($this->subscriberPHIDs) {
      $joins[] = qsprintf(
        $conn_r,
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
            $conn_r,
            'LEFT JOIN %T projectGroup ON task.phid = projectGroup.src
              AND projectGroup.type = %d
              AND projectGroup.dst NOT IN (%Ls)',
            $edge_table,
            PhabricatorProjectObjectHasProjectEdgeType::EDGECONST,
            $ignore_group_phids);
        } else {
          $joins[] = qsprintf(
            $conn_r,
            'LEFT JOIN %T projectGroup ON task.phid = projectGroup.src
              AND projectGroup.type = %d',
            $edge_table,
            PhabricatorProjectObjectHasProjectEdgeType::EDGECONST);
        }
        $joins[] = qsprintf(
          $conn_r,
          'LEFT JOIN %T projectGroupName
            ON projectGroup.dst = projectGroupName.indexedObjectPHID',
          id(new ManiphestNameIndex())->getTableName());
        break;
    }

    $joins[] = parent::buildJoinClauseParts($conn_r);

    return $joins;
  }

  protected function buildGroupClause(AphrontDatabaseConnection $conn_r) {
    $joined_multiple_rows = $this->shouldJoinBlockingTasks() ||
                            $this->shouldJoinBlockedTasks() ||
                            ($this->shouldGroupQueryResultRows());

    $joined_project_name = ($this->groupBy == self::GROUP_PROJECT);

    // If we're joining multiple rows, we need to group the results by the
    // task IDs.
    if ($joined_multiple_rows) {
      if ($joined_project_name) {
        return 'GROUP BY task.phid, projectGroup.dst';
      } else {
        return 'GROUP BY task.phid';
      }
    } else {
      return '';
    }
  }

  /**
   * Return project PHIDs which we should ignore when grouping tasks by
   * project. For example, if a user issues a query like:
   *
   *   Tasks in all projects: Frontend, Bugs
   *
   * ...then we don't show "Frontend" or "Bugs" groups in the result set, since
   * they're meaningless as all results are in both groups.
   *
   * Similarly, for queries like:
   *
   *   Tasks in any projects: Public Relations
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

  protected function getResultCursor($result) {
    $id = $result->getID();

    if ($this->groupBy == self::GROUP_PROJECT) {
      return rtrim($id.'.'.$result->getGroupByProjectPHID(), '.');
    }

    return $id;
  }

  public function getBuiltinOrders() {
    $orders = array(
      'priority' => array(
        'vector' => array('priority', 'subpriority', 'id'),
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
      'subpriority' => array(
        'table' => 'task',
        'column' => 'subpriority',
        'type' => 'float',
      ),
      'updated' => array(
        'table' => 'task',
        'column' => 'dateModified',
        'type' => 'int',
      ),
    );
  }

  protected function getPagingValueMap($cursor, array $keys) {
    $cursor_parts = explode('.', $cursor, 2);
    $task_id = $cursor_parts[0];
    $group_id = idx($cursor_parts, 1);

    $task = $this->loadCursorObject($task_id);

    $map = array(
      'id' => $task->getID(),
      'priority' => $task->getPriority(),
      'subpriority' => $task->getSubpriority(),
      'owner' => $task->getOwnerOrdering(),
      'status' => $task->getStatus(),
      'title' => $task->getTitle(),
      'updated' => $task->getDateModified(),
    );

    foreach ($keys as $key) {
      switch ($key) {
        case 'project':
          $value = null;
          if ($group_id) {
            $paging_projects = id(new PhabricatorProjectQuery())
              ->setViewer($this->getViewer())
              ->withPHIDs(array($group_id))
              ->execute();
            if ($paging_projects) {
              $value = head($paging_projects)->getName();
            }
          }
          $map[$key] = $value;
          break;
      }
    }

    foreach ($keys as $key) {
      if ($this->isCustomFieldOrderKey($key)) {
        $map += $this->getPagingValueMapForCustomFields($task);
        break;
      }
    }

    return $map;
  }

  protected function getPrimaryTableAlias() {
    return 'task';
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorManiphestApplication';
  }

}
