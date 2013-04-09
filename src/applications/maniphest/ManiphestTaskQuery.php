<?php

/**
 * Query tasks by specific criteria. This class uses the higher-performance
 * but less-general Maniphest indexes to satisfy queries.
 *
 * @group maniphest
 */
final class ManiphestTaskQuery extends PhabricatorQuery {

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

  private $priority         = null;

  private $minPriority      = null;
  private $maxPriority      = null;

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

  private $limit            = null;
  const DEFAULT_PAGE_SIZE   = 1000;

  private $offset           = 0;
  private $calculateRows    = false;

  private $rowCount         = null;

  private $groupByProjectResults = null; // See comment at bottom for details
  private $viewer;

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function getViewer() {
    return $this->viewer;
  }

  public function withAuthors(array $authors) {
    $this->authorPHIDs = $authors;
    return $this;
  }

  public function withTaskIDs(array $ids) {
    $this->taskIDs = $ids;
    return $this;
  }

  public function withTaskPHIDs(array $phids) {
    $this->taskPHIDs = $phids;
    return $this;
  }

  public function withOwners(array $owners) {
    $this->includeUnowned = false;
    foreach ($owners as $k => $phid) {
      if ($phid == ManiphestTaskOwner::OWNER_UP_FOR_GRABS) {
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

  public function withPriority($priority) {
    $this->priority = $priority;
    return $this;
  }

  public function withPrioritiesBetween($min, $max) {
    $this->minPriority = $min;
    $this->maxPriority = $max;
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

  public function setLimit($limit) {
    $this->limit = $limit;
    return $this;
  }

  public function setOffset($offset) {
    $this->offset = $offset;
    return $this;
  }

  public function setCalculateRows($calculate_rows) {
    $this->calculateRows = $calculate_rows;
    return $this;
  }

  public function getRowCount() {
    if ($this->rowCount === null) {
      throw new Exception(
        "You must execute a query with setCalculateRows() before you can ".
        "retrieve a row count.");
    }
    return $this->rowCount;
  }

  public function getGroupByProjectResults() {
    return $this->groupByProjectResults;
  }

  public function withAnyProjects(array $projects) {
    $this->anyProjectPHIDs = $projects;
    return $this;
  }

  public function withAnyUserProjects(array $users) {
    $this->anyUserProjectPHIDs = $users;
    return $this;
  }

  public function execute() {

    $task_dao = new ManiphestTask();
    $conn = $task_dao->establishConnection('r');

    if ($this->calculateRows) {
      $calc = 'SQL_CALC_FOUND_ROWS';
    } else {
      $calc = '';
    }

    $where = array();
    $where[] = $this->buildTaskIDsWhereClause($conn);
    $where[] = $this->buildTaskPHIDsWhereClause($conn);
    $where[] = $this->buildStatusWhereClause($conn);
    $where[] = $this->buildPriorityWhereClause($conn);
    $where[] = $this->buildAuthorWhereClause($conn);
    $where[] = $this->buildOwnerWhereClause($conn);
    $where[] = $this->buildSubscriberWhereClause($conn);
    $where[] = $this->buildProjectWhereClause($conn);
    $where[] = $this->buildAnyProjectWhereClause($conn);
    $where[] = $this->buildAnyUserProjectWhereClause($conn);
    $where[] = $this->buildXProjectWhereClause($conn);
    $where[] = $this->buildFullTextWhereClause($conn);

    $where = $this->formatWhereClause($where);

    $join = array();
    $join[] = $this->buildProjectJoinClause($conn);
    $join[] = $this->buildAnyProjectJoinClause($conn);
    $join[] = $this->buildXProjectJoinClause($conn);
    $join[] = $this->buildSubscriberJoinClause($conn);

    $join = array_filter($join);
    if ($join) {
      $join = implode(' ', $join);
    } else {
      $join = '';
    }

    $having = '';
    $count = '';
    $group = '';

    if (count($this->projectPHIDs) > 1 || count($this->anyProjectPHIDs) > 1) {
      // If we're joining multiple rows, we need to group the results by the
      // task IDs.
      $group = 'GROUP BY task.id';
    } else {
      $group = '';
    }

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

    $order = $this->buildOrderClause($conn);

    $offset = (int)nonempty($this->offset, 0);
    $limit  = (int)nonempty($this->limit, self::DEFAULT_PAGE_SIZE);

    if ($this->groupBy == self::GROUP_PROJECT) {
      $limit  = PHP_INT_MAX;
      $offset = 0;
    }

    $data = queryfx_all(
      $conn,
      'SELECT %Q * %Q FROM %T task %Q %Q %Q %Q %Q LIMIT %d, %d',
      $calc,
      $count,
      $task_dao->getTableName(),
      $join,
      $where,
      $group,
      $having,
      $order,
      $offset,
      $limit);

    if ($this->calculateRows) {
      $count = queryfx_one(
        $conn,
        'SELECT FOUND_ROWS() N');
      $this->rowCount = $count['N'];
    } else {
      $this->rowCount = null;
    }

    $tasks = $task_dao->loadAllFromArray($data);

    if ($this->groupBy == self::GROUP_PROJECT) {
      $tasks = $this->applyGroupByProject($tasks);
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
        return 'status = 0';
      case self::STATUS_CLOSED:
        return 'status > 0';
      default:
        $constant = idx($map, $this->status);
        if (!$constant) {
          throw new Exception("Unknown status query '{$this->status}'!");
        }
        return qsprintf(
          $conn,
          'status = %d',
          $constant);
    }
  }

  private function buildPriorityWhereClause(AphrontDatabaseConnection $conn) {
    if ($this->priority !== null) {
      return qsprintf(
        $conn,
        'priority = %d',
        $this->priority);
    } elseif ($this->minPriority !== null && $this->maxPriority !== null) {
      return qsprintf(
        $conn,
        'priority >= %d AND priority <= %d',
        $this->minPriority,
        $this->maxPriority);
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
    if (!$this->fullTextSearch) {
      return null;
    }

    // In doing a fulltext search, we first find all the PHIDs that match the
    // fulltext search, and then use that to limit the rest of the search
    $fulltext_query = new PhabricatorSearchQuery();
    $fulltext_query->setQuery($this->fullTextSearch);
    $fulltext_query->setParameter('limit', PHP_INT_MAX);

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

  private function buildProjectJoinClause(AphrontDatabaseConnection $conn) {
    if (!$this->projectPHIDs && !$this->includeNoProject) {
      return null;
    }

    $project_dao = new ManiphestTaskProject();
    return qsprintf(
      $conn,
      '%Q JOIN %T project ON project.taskPHID = task.phid',
      ($this->includeNoProject ? 'LEFT' : ''),
      $project_dao->getTableName());
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
      ->setViewer($this->viewer)
      ->withMemberPHIDs($this->anyUserProjectPHIDs)
      ->execute();
    $any_user_project_phids = mpull($projects, 'getPHID');

    return qsprintf(
      $conn,
      'anyproject.projectPHID IN (%Ls)',
      $any_user_project_phids);
  }

  private function buildAnyProjectJoinClause(AphrontDatabaseConnection $conn) {
    if (!$this->anyProjectPHIDs && !$this->anyUserProjectPHIDs) {
      return null;
    }

    $project_dao = new ManiphestTaskProject();
    return qsprintf(
      $conn,
      'JOIN %T anyproject ON anyproject.taskPHID = task.phid',
      $project_dao->getTableName());
  }

  private function buildXProjectWhereClause(AphrontDatabaseConnection $conn) {
    if (!$this->xprojectPHIDs) {
      return null;
    }

    return qsprintf(
      $conn,
      'xproject.projectPHID IS NULL');
  }

  private function buildXProjectJoinClause(AphrontDatabaseConnection $conn) {
    if (!$this->xprojectPHIDs) {
      return null;
    }

    $project_dao = new ManiphestTaskProject();
    return qsprintf(
      $conn,
      'LEFT JOIN %T xproject ON xproject.taskPHID = task.phid
        AND xproject.projectPHID IN (%Ls)',
      $project_dao->getTableName(),
      $this->xprojectPHIDs);
  }

  private function buildSubscriberJoinClause(AphrontDatabaseConnection $conn) {
    if (!$this->subscriberPHIDs) {
      return null;
    }

    $subscriber_dao = new ManiphestTaskSubscriber();
    return qsprintf(
      $conn,
      'JOIN %T subscriber ON subscriber.taskPHID = task.phid',
      $subscriber_dao->getTableName());
  }

  private function buildOrderClause(AphrontDatabaseConnection $conn) {
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
        // NOTE: We have to load the entire result set and apply this grouping
        // in the PHP process for now.
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

    foreach ($order as $k => $column) {
      switch ($column) {
        case 'subpriority':
        case 'ownerOrdering':
        case 'title':
          $order[$k] = "task.{$column} ASC";
          break;
        default:
          $order[$k] = "task.{$column} DESC";
          break;
      }
    }

    return 'ORDER BY '.implode(', ', $order);
  }


  /**
   * To get paging to work for "group by project", we need to do a bunch of
   * server-side magic since there's currently no way to sort by project name on
   * the database.
   *
   * As a consequence of this, moreover, because the list we return from here
   * may include a single task multiple times (once for each project it's in),
   * sorting gets screwed up in the controller unless we tell it which project
   * to put the task in each time it appears. Hence the magic field
   * groupByProjectResults.
   *
   * TODO: Move this all to the database.
   */
  private function applyGroupByProject(array $tasks) {
    assert_instances_of($tasks, 'ManiphestTask');

    $project_phids = array();
    foreach ($tasks as $task) {
      foreach ($task->getProjectPHIDs() as $phid) {
        $project_phids[$phid] = true;
      }
    }

    // TODO: This should use the query's viewer once this class extends
    // PhabricatorPolicyQuery (T603).

    $handles = id(new PhabricatorObjectHandleData(array_keys($project_phids)))
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->loadHandles();

    $max = 1;
    foreach ($handles as $handle) {
      $max = max($max, strlen($handle->getName()));
    }

    $items = array();
    $ii = 0;
    foreach ($tasks as $key => $task) {
      $phids = $task->getProjectPHIDs();
      if ($this->projectPHIDs) {
        $phids = array_diff($phids, $this->projectPHIDs);
      }
      if ($phids) {
        foreach ($phids as $phid) {
          $items[] = array(
            'key'  => $key,
            'proj' => $phid,
            'seq'  => sprintf(
              '%'.$max.'s%09d',
              $handles[$phid]->getName(),
              $ii),
          );
        }
      } else {
        // Sort "no project" tasks first.
        $items[] = array(
          'key'  => $key,
          'proj' => null,
          'seq'  => sprintf(
            '%'.$max.'s%09d',
            '',
            $ii),
        );
      }
      ++$ii;
    }

    $items = isort($items, 'seq');
    $items = array_slice(
      $items,
      nonempty($this->offset),
      nonempty($this->limit, self::DEFAULT_PAGE_SIZE));

    $result = array();
    $projects = array();
    foreach ($items as $item) {
      $result[] = $projects[$item['proj']][] = $tasks[$item['key']];
    }
    $this->groupByProjectResults = $projects;

    return $result;
  }

}
