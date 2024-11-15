<?php

final class PhabricatorRoleColumnQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $rolePHIDs;
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

  public function withRolePHIDs(array $role_phids) {
    $this->rolePHIDs = $role_phids;
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
    return new PhabricatorRoleColumn();
  }

  protected function loadPage() {
    return $this->loadStandardPage($this->newResultObject());
  }

  protected function willFilterPage(array $page) {
    $roles = array();

    $role_phids = array_filter(mpull($page, 'getRolePHID'));
    if ($role_phids) {
      $roles = id(new PhabricatorRoleQuery())
        ->setParentQuery($this)
        ->setViewer($this->getViewer())
        ->withPHIDs($role_phids)
        ->execute();
      $roles = mpull($roles, null, 'getPHID');
    }

    foreach ($page as $key => $column) {
      $phid = $column->getRolePHID();
      $role = idx($roles, $phid);
      if (!$role) {
        $this->didRejectResult($page[$key]);
        unset($page[$key]);
        continue;
      }
      $column->attachRole($role);
    }

    $proxy_phids = array_filter(mpull($page, 'getRolePHID'));

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
        if (!($proxy instanceof PhabricatorRoleColumnProxyInterface)) {
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
        $triggers = id(new PhabricatorRoleTriggerQuery())
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

    if ($this->rolePHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'rolePHID IN (%Ls)',
        $this->rolePHIDs);
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
    return 'PhabricatorRoleApplication';
  }

}
