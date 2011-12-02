<?php

/*
 * Copyright 2011 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * Query tasks by specific criteria. This class uses the higher-performance
 * but less-general Maniphest indexes to satisfy queries.
 *
 * @group maniphest
 */
final class ManiphestTaskQuery {

  private $taskIDs          = array();
  private $authorPHIDs      = array();
  private $ownerPHIDs       = array();
  private $includeUnowned   = null;
  private $projectPHIDs     = array();
  private $subscriberPHIDs  = array();
  private $anyProject       = false;

  private $status           = 'status-any';
  const STATUS_ANY          = 'status-any';
  const STATUS_OPEN         = 'status-open';
  const STATUS_CLOSED       = 'status-closed';

  private $priority         = null;

  private $groupBy          = 'group-none';
  const GROUP_NONE          = 'group-none';
  const GROUP_PRIORITY      = 'group-priority';
  const GROUP_OWNER         = 'group-owner';
  const GROUP_STATUS        = 'group-status';

  private $orderBy          = 'order-modified';
  const ORDER_PRIORITY      = 'order-priority';
  const ORDER_CREATED       = 'order-created';
  const ORDER_MODIFIED      = 'order-modified';

  private $limit            = null;
  const DEFAULT_PAGE_SIZE   = 1000;

  private $offset           = 0;
  private $calculateRows    = false;

  private $rowCount         = null;


  public function withAuthors(array $authors) {
    $this->authorPHIDs = $authors;
    return $this;
  }

  public function withTaskIDs(array $ids) {
    $this->taskIDs = $ids;
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

  public function withProjects(array $projects) {
    $this->projectPHIDs = $projects;
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

  public function withSubscribers(array $subscribers) {
    $this->subscriberPHIDs = $subscribers;
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

  public function withAnyProject($any_project) {
    $this->anyProject = $any_project;
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
    $where[] = $this->buildStatusWhereClause($conn);
    $where[] = $this->buildPriorityWhereClause($conn);
    $where[] = $this->buildAuthorWhereClause($conn);
    $where[] = $this->buildOwnerWhereClause($conn);
    $where[] = $this->buildSubscriberWhereClause($conn);
    $where[] = $this->buildProjectWhereClause($conn);

    $where = array_filter($where);
    if ($where) {
      $where = 'WHERE ('.implode(') AND (', $where).')';
    } else {
      $where = '';
    }

    $join = array();
    $join[] = $this->buildProjectJoinClause($conn);
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
    if (count($this->projectPHIDs) > 1) {

      // If we're searching for more than one project:
      //  - We'll get multiple rows for tasks when they join the project table
      //    multiple times. We use GROUP BY to make them distinct again.
      //  - We want to treat the query as an intersection query, not a union
      //    query. We sum the project count and require it be the same as the
      //    number of projects we're searching for. (If 'anyProject' is set,
      //    we do union instead.)

      $group = 'GROUP BY task.id';

      if (!$this->anyProject) {
        $count = ', COUNT(1) projectCount';
        $having = qsprintf(
          $conn,
          'HAVING projectCount = %d',
          count($this->projectPHIDs));
      }
    }

    $order = $this->buildOrderClause($conn);

    $offset = (int)nonempty($this->offset, 0);
    $limit  = (int)nonempty($this->limit, self::DEFAULT_PAGE_SIZE);

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

    return $task_dao->loadAllFromArray($data);
  }

  private function buildTaskIDsWhereClause($conn) {
    if (!$this->taskIDs) {
      return null;
    }

    return qsprintf(
      $conn,
      'id in (%Ld)',
      $this->taskIDs);
  }

  private function buildStatusWhereClause($conn) {
    switch ($this->status) {
      case self::STATUS_ANY:
        return null;
      case self::STATUS_OPEN:
        return 'status = 0';
      case self::STATUS_CLOSED:
        return 'status > 0';
      default:
        throw new Exception("Unknown status query '{$this->status}'!");
    }
  }

  private function buildPriorityWhereClause($conn) {
    if ($this->priority === null) {
      return null;
    }

    return qsprintf(
      $conn,
      'priority = %d',
      $this->priority);
  }

  private function buildAuthorWhereClause($conn) {
    if (!$this->authorPHIDs) {
      return null;
    }

    return qsprintf(
      $conn,
      'authorPHID in (%Ls)',
      $this->authorPHIDs);
  }

  private function buildOwnerWhereClause($conn) {
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

  private function buildSubscriberWhereClause($conn) {
    if (!$this->subscriberPHIDs) {
      return null;
    }

    return qsprintf(
      $conn,
      'subscriber.subscriberPHID IN (%Ls)',
      $this->subscriberPHIDs);
  }

  private function buildProjectWhereClause($conn) {
    if (!$this->projectPHIDs) {
      return null;
    }

    return qsprintf(
      $conn,
      'project.projectPHID IN (%Ls)',
      $this->projectPHIDs);
  }

  private function buildProjectJoinClause($conn) {
    if (!$this->projectPHIDs) {
      return null;
    }

    $project_dao = new ManiphestTaskProject();
    return qsprintf(
      $conn,
      'JOIN %T project ON project.taskPHID = task.phid',
      $project_dao->getTableName());
  }

  private function buildSubscriberJoinClause($conn) {
    if (!$this->subscriberPHIDs) {
      return null;
    }

    $subscriber_dao = new ManiphestTaskSubscriber();
    return qsprintf(
      $conn,
      'JOIN %T subscriber ON subscriber.taskPHID = task.phid',
      $subscriber_dao->getTableName());
  }

  private function buildOrderClause($conn) {
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
      default:
        throw new Exception("Unknown group query '{$this->groupBy}'!");
    }

    switch ($this->orderBy) {
      case self::ORDER_PRIORITY:
        $order[] = 'priority';
        $order[] = 'dateModified';
        break;
      case self::ORDER_CREATED:
        $order[] = 'id';
        break;
      case self::ORDER_MODIFIED:
        $order[] = 'dateModified';
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
        case 'ownerOrdering':
          $order[$k] = "task.{$column} ASC";
          break;
        default:
          $order[$k] = "task.{$column} DESC";
          break;
      }
    }

    return 'ORDER BY '.implode(', ', $order);
  }


}
