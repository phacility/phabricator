<?php

final class HarbormasterBuildableQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $buildablePHIDs;
  private $containerPHIDs;
  private $manualBuildables;

  private $needContainerObjects;
  private $needContainerHandles;
  private $needBuildableHandles;
  private $needBuilds;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withBuildablePHIDs(array $buildable_phids) {
    $this->buildablePHIDs = $buildable_phids;
    return $this;
  }

  public function withContainerPHIDs(array $container_phids) {
    $this->containerPHIDs = $container_phids;
    return $this;
  }

  public function withManualBuildables($manual) {
    $this->manualBuildables = $manual;
    return $this;
  }

  public function needContainerObjects($need) {
    $this->needContainerObjects = $need;
    return $this;
  }

  public function needContainerHandles($need) {
    $this->needContainerHandles = $need;
    return $this;
  }

  public function needBuildableHandles($need) {
    $this->needBuildableHandles = $need;
    return $this;
  }

  public function needBuilds($need) {
    $this->needBuilds = $need;
    return $this;
  }

  protected function loadPage() {
    $table = new HarbormasterBuildable();
    $conn_r = $table->establishConnection('r');

    $data = queryfx_all(
      $conn_r,
      'SELECT * FROM %T %Q %Q %Q',
      $table->getTableName(),
      $this->buildWhereClause($conn_r),
      $this->buildOrderClause($conn_r),
      $this->buildLimitClause($conn_r));

    return $table->loadAllFromArray($data);
  }

  protected function willFilterPage(array $page) {
    $buildables = array();

    $buildable_phids = array_filter(mpull($page, 'getBuildablePHID'));
    if ($buildable_phids) {
      $buildables = id(new PhabricatorObjectQuery())
        ->setViewer($this->getViewer())
        ->withPHIDs($buildable_phids)
        ->setParentQuery($this)
        ->execute();
      $buildables = mpull($buildables, null, 'getPHID');
    }

    foreach ($page as $key => $buildable) {
      $buildable_phid = $buildable->getBuildablePHID();
      if (empty($buildables[$buildable_phid])) {
        unset($page[$key]);
        continue;
      }
      $buildable->attachBuildableObject($buildables[$buildable_phid]);
    }

    return $page;
  }

  protected function didFilterPage(array $page) {
    if ($this->needContainerObjects || $this->needContainerHandles) {
      $container_phids = array_filter(mpull($page, 'getContainerPHID'));

      if ($this->needContainerObjects) {
        $containers = array();

        if ($container_phids) {
          $containers = id(new PhabricatorObjectQuery())
            ->setViewer($this->getViewer())
            ->withPHIDs($container_phids)
            ->setParentQuery($this)
            ->execute();
          $containers = mpull($containers, null, 'getPHID');
        }

        foreach ($page as $key => $buildable) {
          $container_phid = $buildable->getContainerPHID();
          $buildable->attachContainerObject(idx($containers, $container_phid));
        }
      }

      if ($this->needContainerHandles) {
        $handles = array();

        if ($container_phids) {
          $handles = id(new PhabricatorHandleQuery())
            ->setViewer($this->getViewer())
            ->withPHIDs($container_phids)
            ->setParentQuery($this)
            ->execute();
        }

        foreach ($page as $key => $buildable) {
          $container_phid = $buildable->getContainerPHID();
          $buildable->attachContainerHandle(idx($handles, $container_phid));
        }
      }
    }

    if ($this->needBuildableHandles) {
      $handles = array();

      $handle_phids = array_filter(mpull($page, 'getBuildablePHID'));
      if ($handle_phids) {
        $handles = id(new PhabricatorHandleQuery())
          ->setViewer($this->getViewer())
          ->withPHIDs($handle_phids)
          ->setParentQuery($this)
          ->execute();
      }

      foreach ($page as $key => $buildable) {
        $handle_phid = $buildable->getBuildablePHID();
        $buildable->attachBuildableHandle(idx($handles, $handle_phid));
      }
    }

    if ($this->needBuilds) {
      $builds = id(new HarbormasterBuildQuery())
        ->setViewer($this->getViewer())
        ->setParentQuery($this)
        ->withBuildablePHIDs(mpull($page, 'getPHID'))
        ->execute();
      $builds = mgroup($builds, 'getBuildablePHID');
      foreach ($page as $key => $buildable) {
        $buildable->attachBuilds(idx($builds, $buildable->getPHID(), array()));
      }
    }

    return $page;
  }

  protected function buildWhereClause(AphrontDatabaseConnection $conn_r) {
    $where = array();

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn_r,
        'id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn_r,
        'phid IN (%Ls)',
        $this->phids);
    }

    if ($this->buildablePHIDs !== null) {
      $where[] = qsprintf(
        $conn_r,
        'buildablePHID IN (%Ls)',
        $this->buildablePHIDs);
    }

    if ($this->containerPHIDs !== null) {
      $where[] = qsprintf(
        $conn_r,
        'containerPHID in (%Ls)',
        $this->containerPHIDs);
    }

    if ($this->manualBuildables !== null) {
      $where[] = qsprintf(
        $conn_r,
        'isManualBuildable = %d',
        (int)$this->manualBuildables);
    }

    $where[] = $this->buildPagingClause($conn_r);

    return $this->formatWhereClause($where);
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorHarbormasterApplication';
  }

}
