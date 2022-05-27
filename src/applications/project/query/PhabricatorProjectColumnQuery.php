<?php

final class PhabricatorProjectColumnQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $projectPHIDs;
  private $proxyPHIDs;
  private $statuses;
  private $isProxyColumn;
  private $triggerPHIDs;
  private $needTriggers;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withProjectPHIDs(array $project_phids) {
    $this->projectPHIDs = $project_phids;
    return $this;
  }

  public function withProxyPHIDs(array $proxy_phids) {
    $this->proxyPHIDs = $proxy_phids;
    return $this;
  }

  public function withStatuses(array $status) {
    $this->statuses = $status;
    return $this;
  }

  public function withIsProxyColumn($is_proxy) {
    $this->isProxyColumn = $is_proxy;
    return $this;
  }

  public function withTriggerPHIDs(array $trigger_phids) {
    $this->triggerPHIDs = $trigger_phids;
    return $this;
  }

  public function needTriggers($need_triggers) {
    $this->needTriggers = true;
    return $this;
  }

  public function newResultObject() {
    return new PhabricatorProjectColumn();
  }

  protected function willFilterPage(array $page) {
    $projects = array();

    $project_phids = array_filter(mpull($page, 'getProjectPHID'));
    if ($project_phids) {
      $projects = id(new PhabricatorProjectQuery())
        ->setParentQuery($this)
        ->setViewer($this->getViewer())
        ->withPHIDs($project_phids)
        ->execute();
      $projects = mpull($projects, null, 'getPHID');
    }

    foreach ($page as $key => $column) {
      $phid = $column->getProjectPHID();
      $project = idx($projects, $phid);
      if (!$project) {
        $this->didRejectResult($page[$key]);
        unset($page[$key]);
        continue;
      }
      $column->attachProject($project);
    }

    $proxy_phids = array_filter(mpull($page, 'getProjectPHID'));

    return $page;
  }

  protected function didFilterPage(array $page) {
    $proxy_phids = array();
    foreach ($page as $column) {
      $proxy_phid = $column->getProxyPHID();
      if ($proxy_phid !== null) {
        $proxy_phids[$proxy_phid] = $proxy_phid;
      }
    }

    if ($proxy_phids) {
      $proxies = id(new PhabricatorObjectQuery())
        ->setParentQuery($this)
        ->setViewer($this->getViewer())
        ->withPHIDs($proxy_phids)
        ->execute();
      $proxies = mpull($proxies, null, 'getPHID');
    } else {
      $proxies = array();
    }

    foreach ($page as $key => $column) {
      $proxy_phid = $column->getProxyPHID();

      if ($proxy_phid !== null) {
        $proxy = idx($proxies, $proxy_phid);

        // Only attach valid proxies, so we don't end up getting surprised if
        // an install somehow gets junk into their database.
        if (!($proxy instanceof PhabricatorColumnProxyInterface)) {
          $proxy = null;
        }

        if (!$proxy) {
          $this->didRejectResult($column);
          unset($page[$key]);
          continue;
        }
      } else {
        $proxy = null;
      }

      $column->attachProxy($proxy);
    }

    if ($this->needTriggers) {
      $trigger_phids = array();
      foreach ($page as $column) {
        if ($column->canHaveTrigger()) {
          $trigger_phid = $column->getTriggerPHID();
          if ($trigger_phid) {
            $trigger_phids[] = $trigger_phid;
          }
        }
      }

      if ($trigger_phids) {
        $triggers = id(new PhabricatorProjectTriggerQuery())
          ->setViewer($this->getViewer())
          ->setParentQuery($this)
          ->withPHIDs($trigger_phids)
          ->execute();
        $triggers = mpull($triggers, null, 'getPHID');
      } else {
        $triggers = array();
      }

      foreach ($page as $column) {
        $trigger = null;

        if ($column->canHaveTrigger()) {
          $trigger_phid = $column->getTriggerPHID();
          if ($trigger_phid) {
            $trigger = idx($triggers, $trigger_phid);
          }
        }

        $column->attachTrigger($trigger);
      }
    }

    return $page;
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn,
        'phid IN (%Ls)',
        $this->phids);
    }

    if ($this->projectPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'projectPHID IN (%Ls)',
        $this->projectPHIDs);
    }

    if ($this->proxyPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'proxyPHID IN (%Ls)',
        $this->proxyPHIDs);
    }

    if ($this->statuses !== null) {
      $where[] = qsprintf(
        $conn,
        'status IN (%Ld)',
        $this->statuses);
    }

    if ($this->triggerPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'triggerPHID IN (%Ls)',
        $this->triggerPHIDs);
    }

    if ($this->isProxyColumn !== null) {
      if ($this->isProxyColumn) {
        $where[] = qsprintf($conn, 'proxyPHID IS NOT NULL');
      } else {
        $where[] = qsprintf($conn, 'proxyPHID IS NULL');
      }
    }

    return $where;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorProjectApplication';
  }

}
