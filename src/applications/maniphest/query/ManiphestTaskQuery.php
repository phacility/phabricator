<?php

/**
 * Query tasks by specific criteria. This class uses the higher-performance
 * but less-general Maniphest indexes to satisfy queries.
 *
 * @group maniphest
 */
final class ManiphestTaskQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $taskIDs             = array();
  private $taskPHIDs           = array();
  private $authorPHIDs         = array();
  private $ownerPHIDs          = array();
  private $includeUnowned      = null;
  private $projectPHIDs        = array();
  private $xprojectPHIDs       = array();
  private $subscriberPHIDs     = array();
  private $anyProjectPHIDs     = array();
  private $anyUserProjectPHIDs = array();
  private $includeNoProject    = null;
  private $dateCreatedAfter;
  private $dateCreatedBefore;
  private $dateModifiedAfter;
  private $dateModifiedBefore;

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

  private $groupBy          = 'group-none';
  const GROUP_NONE          = 'group-none';
  const GROUP_PRIORITY      = 'group-priority';
  const GROUP_OWNER         = 'group-owner';
  const GROUP_STATUS        = 'group-status';
  const GROUP_PROJECT       = 'group-project';

  private $orderBy          = 'order-modified';
  const ORDER_PRIORITY      = 'order-priority';
  const ORDER_CREATED       = 'order-created';
  const ORDER_MODIFIED      = 'order-modified';
  const ORDER_TITLE         = 'order-title';

  const DEFAULT_PAGE_SIZE   = 1000;

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
    $this->includeUnowned = false;
    foreach ($owners as $k => $phid) {
      if ($phid == ManiphestTaskOwner::OWNER_UP_FOR_GRABS || $phid === null) {
        $this->includeUnowned = true;
        unset($owners[$k]);
        break;
      }
    }
    $this->ownerPHIDs = $owners;
    return $this;
  }

  public function withAllProjects(array $projects) {
    $this->includeNoProject = false;
    foreach ($projects as $k => $phid) {
      if ($phid == ManiphestTaskOwner::PROJECT_NO_PROJECT) {
        $this->includeNoProject = true;
        unset($projects[$k]);
      }
    }
    $this->projectPHIDs = $projects;
    return $this;
  }

  public function withoutProjects(array $projects) {
    $this->xprojectPHIDs = $projects;
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
    return $this;
  }

  public function setOrderBy($order) {
    $this->orderBy = $order;
    return $this;
  }

  public function withAnyProjects(array $projects) {
    $this->anyProjectPHIDs = $projects;
    return $this;
  }

  public function withAnyUserProjects(array $users) {
    $this->anyUserProjectPHIDs = $users;
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

  public function loadPage() {

    // TODO: (T603) It is possible for a user to find the PHID of a project
    // they can't see, then query for tasks in that project and deduce the
    // identity of unknown/invisible projects. Before we allow the user to
    // execute a project-based PHID query, we should verify that they
    // can see the project.

    $task_dao = new ManiphestTask();
    $conn = $task_dao->establishConnection('r');

    $where = array();
    $where[] = $this->buildTaskIDsWhereClause($conn);
    $where[] = $this->buildTaskPHIDsWhereClause($conn);
    $where[] = $this->buildStatusWhereClause($conn);
    $where[] = $this->buildStatusesWhereClause($conn);
    $where[] = $this->buildPrioritiesWhereClause($conn);
    $where[] = $this->buildAuthorWhereClause($conn);
    $where[] = $this->buildOwnerWhereClause($conn);
    $where[] = $this->buildSubscriberWhereClause($conn);
    $where[] = $this->buildProjectWhereClause($conn);
    $where[] = $this->buildAnyProjectWhereClause($conn);
    $where[] = $this->buildAnyUserProjectWhereClause($conn);
    $where[] = $this->buildXProjectWhereClause($conn);
    $where[] = $this->buildFullTextWhereClause($conn);

    if ($this->dateCreatedAfter) {
      $where[] = qsprintf(
        $conn,
        'dateCreated >= %d',
        $this->dateCreatedAfter);
    }

    if ($this->dateCreatedBefore) {
      $where[] = qsprintf(
        $conn,
        'dateCreated <= %d',
        $this->dateCreatedBefore);
    }

    if ($this->dateModifiedAfter) {
      $where[] = qsprintf(
        $conn,
        'dateModified >= %d',
        $this->dateModifiedAfter);
    }

    if ($this->dateModifiedBefore) {
      $where[] = qsprintf(
        $conn,
        'dateModified <= %d',
        $this->dateModifiedBefore);
    }

    $where[] = $this->buildPagingClause($conn);

    $where = $this->formatWhereClause($where);

    $having = '';
    $count = '';

    if (count($this->projectPHIDs) > 1) {
      // We want to treat the query as an intersection query, not a union
      // query. We sum the project count and require it be the same as the
      // number of projects we're searching for.

      $count = ', COUNT(project.projectPHID) projectCount';
      $having = qsprintf(
        $conn,
        'HAVING projectCount = %d',
        count($this->projectPHIDs));
    }

    $order = $this->buildCustomOrderClause($conn);

    // TODO: Clean up this nonstandardness.
    if (!$this->getLimit()) {
      $this->setLimit(self::DEFAULT_PAGE_SIZE);
    }

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
      'SELECT task.* %Q %Q FROM %T task %Q %Q %Q %Q %Q %Q',
      $count,
      $group_column,
      $task_dao->getTableName(),
      $this->buildJoinsClause($conn),
      $where,
      $this->buildGroupClause($conn),
      $having,
      $order,
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
        if (empty($projects[$task->getGroupByProjectPHID()])) {
          unset($tasks[$key]);
        }
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
      'id in (%Ld)',
      $this->taskIDs);
  }

  private function buildTaskPHIDsWhereClause(AphrontDatabaseConnection $conn) {
    if (!$this->taskPHIDs) {
      return null;
    }

    return qsprintf(
      $conn,
      'phid in (%Ls)',
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
          'status IN (%Ls)',
          ManiphestTaskStatus::getOpenStatusConstants());
      case self::STATUS_CLOSED:
        return qsprintf(
          $conn,
          'status IN (%Ls)',
          ManiphestTaskStatus::getClosedStatusConstants());
      default:
        $constant = idx($map, $this->status);
        if (!$constant) {
          throw new Exception("Unknown status query '{$this->status}'!");
        }
        return qsprintf(
          $conn,
          'status = %s',
          $constant);
    }
  }

  private function buildStatusesWhereClause(AphrontDatabaseConnection $conn) {
    if ($this->statuses) {
      return qsprintf(
        $conn,
        'status IN (%Ls)',
        $this->statuses);
    }
    return null;
  }

  private function buildPrioritiesWhereClause(AphrontDatabaseConnection $conn) {
    if ($this->priorities) {
      return qsprintf(
        $conn,
        'priority IN (%Ld)',
        $this->priorities);
    }

    return null;
  }


  private function buildAuthorWhereClause(AphrontDatabaseConnection $conn) {
    if (!$this->authorPHIDs) {
      return null;
    }

    return qsprintf(
      $conn,
      'authorPHID in (%Ls)',
      $this->authorPHIDs);
  }

  private function buildOwnerWhereClause(AphrontDatabaseConnection $conn) {
    if (!$this->ownerPHIDs) {
      if ($this->includeUnowned === null) {
        return null;
      } else if ($this->includeUnowned) {
        return qsprintf(
          $conn,
          'ownerPHID IS NULL');
      } else {
        return qsprintf(
          $conn,
          'ownerPHID IS NOT NULL');
      }
    }

    if ($this->includeUnowned) {
      return qsprintf(
        $conn,
        'ownerPHID IN (%Ls) OR ownerPHID IS NULL',
        $this->ownerPHIDs);
    } else {
      return qsprintf(
        $conn,
        'ownerPHID IN (%Ls)',
        $this->ownerPHIDs);
    }
  }

  private function buildFullTextWhereClause(AphrontDatabaseConnection $conn) {
    if (!strlen($this->fullTextSearch)) {
      return null;
    }

    // In doing a fulltext search, we first find all the PHIDs that match the
    // fulltext search, and then use that to limit the rest of the search
    $fulltext_query = id(new PhabricatorSavedQuery())
      ->setEngineClassName('PhabricatorSearchApplicaionSearchEngine')
      ->setParameter('query', $this->fullTextSearch);

    // NOTE: Setting this to something larger than 2^53 will raise errors in
    // ElasticSearch, and billions of results won't fit in memory anyway.
    $fulltext_query->setParameter('limit', 100000);
    $fulltext_query->setParameter('type', ManiphestPHIDTypeTask::TYPECONST);

    $engine = PhabricatorSearchEngineSelector::newSelector()->newEngine();
    $fulltext_results = $engine->executeSearch($fulltext_query);

    if (empty($fulltext_results)) {
      $fulltext_results = array(null);
    }

    return qsprintf(
      $conn,
      'phid IN (%Ls)',
      $fulltext_results);
  }

  private function buildSubscriberWhereClause(AphrontDatabaseConnection $conn) {
    if (!$this->subscriberPHIDs) {
      return null;
    }

    return qsprintf(
      $conn,
      'subscriber.subscriberPHID IN (%Ls)',
      $this->subscriberPHIDs);
  }

  private function buildProjectWhereClause(AphrontDatabaseConnection $conn) {
    if (!$this->projectPHIDs && !$this->includeNoProject) {
      return null;
    }

    $parts = array();
    if ($this->projectPHIDs) {
      $parts[] = qsprintf(
        $conn,
        'project.projectPHID in (%Ls)',
        $this->projectPHIDs);
    }
    if ($this->includeNoProject) {
      $parts[] = qsprintf(
        $conn,
        'project.projectPHID IS NULL');
    }

    return '('.implode(') OR (', $parts).')';
  }

  private function buildAnyProjectWhereClause(AphrontDatabaseConnection $conn) {
    if (!$this->anyProjectPHIDs) {
      return null;
    }

    return qsprintf(
      $conn,
      'anyproject.projectPHID IN (%Ls)',
      $this->anyProjectPHIDs);
  }

  private function buildAnyUserProjectWhereClause(
    AphrontDatabaseConnection $conn) {
    if (!$this->anyUserProjectPHIDs) {
      return null;
    }

    $projects = id(new PhabricatorProjectQuery())
      ->setViewer($this->getViewer())
      ->withMemberPHIDs($this->anyUserProjectPHIDs)
      ->execute();
    $any_user_project_phids = mpull($projects, 'getPHID');
    if (!$any_user_project_phids) {
      throw new PhabricatorEmptyQueryException();
    }

    return qsprintf(
      $conn,
      'anyproject.projectPHID IN (%Ls)',
      $any_user_project_phids);
  }

  private function buildXProjectWhereClause(AphrontDatabaseConnection $conn) {
    if (!$this->xprojectPHIDs) {
      return null;
    }

    return qsprintf(
      $conn,
      'xproject.projectPHID IS NULL');
  }

  private function buildCustomOrderClause(AphrontDatabaseConnection $conn) {
    $order = array();

    switch ($this->groupBy) {
      case self::GROUP_NONE:
        break;
      case self::GROUP_PRIORITY:
        $order[] = 'priority';
        break;
      case self::GROUP_OWNER:
        $order[] = 'ownerOrdering';
        break;
      case self::GROUP_STATUS:
        $order[] = 'status';
        break;
      case self::GROUP_PROJECT:
        $order[] = '<group.project>';
        break;
      default:
        throw new Exception("Unknown group query '{$this->groupBy}'!");
    }

    switch ($this->orderBy) {
      case self::ORDER_PRIORITY:
        $order[] = 'priority';
        $order[] = 'subpriority';
        $order[] = 'dateModified';
        break;
      case self::ORDER_CREATED:
        $order[] = 'id';
        break;
      case self::ORDER_MODIFIED:
        $order[] = 'dateModified';
        break;
      case self::ORDER_TITLE:
        $order[] = 'title';
        break;
      default:
        throw new Exception("Unknown order query '{$this->orderBy}'!");
    }

    $order = array_unique($order);

    if (empty($order)) {
      return null;
    }

    $reverse = ($this->getBeforeID() xor $this->getReversePaging());

    foreach ($order as $k => $column) {
      switch ($column) {
        case 'subpriority':
        case 'ownerOrdering':
        case 'title':
          if ($reverse) {
            $order[$k] = "task.{$column} DESC";
          } else {
            $order[$k] = "task.{$column} ASC";
          }
          break;
        case '<group.project>':
          // Put "No Project" at the end of the list.
          if ($reverse) {
            $order[$k] =
              'projectGroupName.indexedObjectName IS NULL DESC, '.
              'projectGroupName.indexedObjectName DESC';
          } else {
            $order[$k] =
              'projectGroupName.indexedObjectName IS NULL ASC, '.
              'projectGroupName.indexedObjectName ASC';
          }
          break;
        default:
          if ($reverse) {
            $order[$k] = "task.{$column} ASC";
          } else {
            $order[$k] = "task.{$column} DESC";
          }
          break;
      }
    }

    return 'ORDER BY '.implode(', ', $order);
  }

  private function buildJoinsClause(AphrontDatabaseConnection $conn_r) {
    $project_dao = new ManiphestTaskProject();

    $joins = array();

    if ($this->projectPHIDs || $this->includeNoProject) {
      $joins[] = qsprintf(
        $conn_r,
        '%Q JOIN %T project ON project.taskPHID = task.phid',
        ($this->includeNoProject ? 'LEFT' : ''),
        $project_dao->getTableName());
    }

    if ($this->anyProjectPHIDs || $this->anyUserProjectPHIDs) {
      $joins[] = qsprintf(
        $conn_r,
        'JOIN %T anyproject ON anyproject.taskPHID = task.phid',
        $project_dao->getTableName());
    }

    if ($this->xprojectPHIDs) {
      $joins[] = qsprintf(
        $conn_r,
        'LEFT JOIN %T xproject ON xproject.taskPHID = task.phid
          AND xproject.projectPHID IN (%Ls)',
        $project_dao->getTableName(),
        $this->xprojectPHIDs);
    }

    if ($this->subscriberPHIDs) {
      $subscriber_dao = new ManiphestTaskSubscriber();
      $joins[] = qsprintf(
        $conn_r,
        'JOIN %T subscriber ON subscriber.taskPHID = task.phid',
        $subscriber_dao->getTableName());
    }

    switch ($this->groupBy) {
      case self::GROUP_PROJECT:
        $ignore_group_phids = $this->getIgnoreGroupedProjectPHIDs();
        if ($ignore_group_phids) {
          $joins[] = qsprintf(
            $conn_r,
            'LEFT JOIN %T projectGroup ON task.phid = projectGroup.taskPHID
              AND projectGroup.projectPHID NOT IN (%Ls)',
            $project_dao->getTableName(),
            $ignore_group_phids);
        } else {
          $joins[] = qsprintf(
            $conn_r,
            'LEFT JOIN %T projectGroup ON task.phid = projectGroup.taskPHID',
            $project_dao->getTableName());
        }
        $joins[] = qsprintf(
          $conn_r,
          'LEFT JOIN %T projectGroupName
            ON projectGroup.projectPHID = projectGroupName.indexedObjectPHID',
          id(new ManiphestNameIndex())->getTableName());
        break;
    }

    $joins[] = $this->buildApplicationSearchJoinClause($conn_r);

    return implode(' ', $joins);
  }

  private function buildGroupClause(AphrontDatabaseConnection $conn_r) {
    $joined_multiple_rows = (count($this->projectPHIDs) > 1) ||
                            (count($this->anyProjectPHIDs) > 1) ||
                            ($this->getApplicationSearchMayJoinMultipleRows());

    $joined_project_name = ($this->groupBy == self::GROUP_PROJECT);

    // If we're joining multiple rows, we need to group the results by the
    // task IDs.
    if ($joined_multiple_rows) {
      if ($joined_project_name) {
        return 'GROUP BY task.phid, projectGroup.projectPHID';
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
    $phids = array();

    if ($this->projectPHIDs) {
      $phids[] = $this->projectPHIDs;
    }

    if (count($this->anyProjectPHIDs) == 1) {
      $phids[] = $this->anyProjectPHIDs;
    }

    // Maybe we should also exclude the "excludeProjectPHIDs"? It won't
    // impact the results, but we might end up with a better query plan.
    // Investigate this on real data? This is likely very rare.

    return array_mergev($phids);
  }

  private function loadCursorObject($id) {
    $results = id(new ManiphestTaskQuery())
      ->setViewer($this->getPagingViewer())
      ->withIDs(array((int)$id))
      ->execute();
    return head($results);
  }

  protected function getPagingValue($result) {
    $id = $result->getID();

    switch ($this->groupBy) {
      case self::GROUP_NONE:
        return $id;
      case self::GROUP_PRIORITY:
        return $id.'.'.$result->getPriority();
      case self::GROUP_OWNER:
        return rtrim($id.'.'.$result->getOwnerPHID(), '.');
      case self::GROUP_STATUS:
        return $id.'.'.$result->getStatus();
      case self::GROUP_PROJECT:
        return rtrim($id.'.'.$result->getGroupByProjectPHID(), '.');
      default:
        throw new Exception("Unknown group query '{$this->groupBy}'!");
    }
  }

  protected function buildPagingClause(AphrontDatabaseConnection $conn_r) {
    $default = parent::buildPagingClause($conn_r);

    $before_id = $this->getBeforeID();
    $after_id = $this->getAfterID();

    if (!$before_id && !$after_id) {
      return $default;
    }

    $cursor_id = nonempty($before_id, $after_id);
    $cursor_parts = explode('.', $cursor_id, 2);
    $task_id = $cursor_parts[0];
    $group_id = idx($cursor_parts, 1);

    $cursor = $this->loadCursorObject($task_id);
    if (!$cursor) {
      return null;
    }

    $columns = array();

    switch ($this->groupBy) {
      case self::GROUP_NONE:
        break;
      case self::GROUP_PRIORITY:
        $columns[] = array(
          'name' => 'task.priority',
          'value' => (int)$group_id,
          'type' => 'int',
        );
        break;
      case self::GROUP_OWNER:
        $columns[] = array(
          'name' => '(task.ownerOrdering IS NULL)',
          'value' => (int)(strlen($group_id) ? 0 : 1),
          'type' => 'int',
        );
        if ($group_id) {
          $paging_users = id(new PhabricatorPeopleQuery())
            ->setViewer($this->getViewer())
            ->withPHIDs(array($group_id))
            ->execute();
          if (!$paging_users) {
            return null;
          }
          $columns[] = array(
            'name' => 'task.ownerOrdering',
            'value' => head($paging_users)->getUsername(),
            'type' => 'string',
            'reverse' => true,
          );
        }
        break;
      case self::GROUP_STATUS:
        $columns[] = array(
          'name' => 'task.status',
          'value' => $group_id,
          'type' => 'string',
        );
        break;
      case self::GROUP_PROJECT:
        $columns[] = array(
          'name' => '(projectGroupName.indexedObjectName IS NULL)',
          'value' => (int)(strlen($group_id) ? 0 : 1),
          'type' => 'int',
        );
        if ($group_id) {
          $paging_projects = id(new PhabricatorProjectQuery())
            ->setViewer($this->getViewer())
            ->withPHIDs(array($group_id))
            ->execute();
          if (!$paging_projects) {
            return null;
          }
          $columns[] = array(
            'name' => 'projectGroupName.indexedObjectName',
            'value' => head($paging_projects)->getName(),
            'type' => 'string',
            'reverse' => true,
          );
        }
        break;
      default:
        throw new Exception("Unknown group query '{$this->groupBy}'!");
    }

    switch ($this->orderBy) {
      case self::ORDER_PRIORITY:
        if ($this->groupBy != self::GROUP_PRIORITY) {
          $columns[] = array(
            'name' => 'task.priority',
            'value' => (int)$cursor->getPriority(),
            'type' => 'int',
          );
        }
        $columns[] = array(
          'name' => 'task.subpriority',
          'value' => (int)$cursor->getSubpriority(),
          'type' => 'int',
          'reverse' => true,
        );
        $columns[] = array(
          'name' => 'task.dateModified',
          'value' => (int)$cursor->getDateModified(),
          'type' => 'int',
        );
        break;
      case self::ORDER_CREATED:
        $columns[] = array(
          'name' => 'task.id',
          'value' => (int)$cursor->getID(),
          'type' => 'int',
        );
        break;
      case self::ORDER_MODIFIED:
        $columns[] = array(
          'name' => 'task.dateModified',
          'value' => (int)$cursor->getDateModified(),
          'type' => 'int',
        );
        break;
      case self::ORDER_TITLE:
        $columns[] = array(
          'name' => 'task.title',
          'value' => $cursor->getTitle(),
          'type' => 'string',
        );
        $columns[] = array(
          'name' => 'task.id',
          'value' => $cursor->getID(),
          'type' => 'int',
        );
        break;
      default:
        throw new Exception("Unknown order query '{$this->orderBy}'!");
    }

    return $this->buildPagingClauseFromMultipleColumns(
      $conn_r,
      $columns,
      array(
        'reversed' => (bool)($before_id xor $this->getReversePaging()),
      ));
  }

  protected function getApplicationSearchObjectPHIDColumn() {
    return 'task.phid';
  }


  public function getQueryApplicationClass() {
    return 'PhabricatorApplicationManiphest';
  }

}
