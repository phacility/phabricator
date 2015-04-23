<?php

final class HarbormasterBuildTargetQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $buildPHIDs;
  private $buildGenerations;
  private $needBuildSteps;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withBuildPHIDs(array $build_phids) {
    $this->buildPHIDs = $build_phids;
    return $this;
  }

  public function withBuildGenerations(array $build_generations) {
    $this->buildGenerations = $build_generations;
    return $this;
  }

  public function needBuildSteps($need_build_steps) {
    $this->needBuildSteps = $need_build_steps;
    return $this;
  }

  protected function loadPage() {
    $table = new HarbormasterBuildTarget();
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

  protected function buildWhereClause(AphrontDatabaseConnection $conn_r) {
    $where = array();

    if ($this->ids) {
      $where[] = qsprintf(
        $conn_r,
        'id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids) {
      $where[] = qsprintf(
        $conn_r,
        'phid in (%Ls)',
        $this->phids);
    }

    if ($this->buildPHIDs) {
      $where[] = qsprintf(
        $conn_r,
        'buildPHID in (%Ls)',
        $this->buildPHIDs);
    }

    if ($this->buildGenerations) {
      $where[] = qsprintf(
        $conn_r,
        'buildGeneration in (%Ld)',
        $this->buildGenerations);
    }

    $where[] = $this->buildPagingClause($conn_r);

    return $this->formatWhereClause($where);
  }

  protected function didFilterPage(array $page) {
    if ($this->needBuildSteps) {
      $step_phids = array();

      foreach ($page as $target) {
        $step_phids[] = $target->getBuildStepPHID();
      }

      $steps = id(new HarbormasterBuildStepQuery())
        ->setViewer($this->getViewer())
        ->setParentQuery($this)
        ->withPHIDs($step_phids)
        ->execute();

      $steps = mpull($steps, null, 'getPHID');

      foreach ($page as $target) {
        $target->attachBuildStep(
          idx($steps, $target->getBuildStepPHID()));
      }
    }

    return $page;
  }

  protected function willFilterPage(array $page) {
    $builds = array();

    $build_phids = array_filter(mpull($page, 'getBuildPHID'));
    if ($build_phids) {
      $builds = id(new PhabricatorObjectQuery())
        ->setViewer($this->getViewer())
        ->withPHIDs($build_phids)
        ->setParentQuery($this)
        ->execute();
      $builds = mpull($builds, null, 'getPHID');
    }

    foreach ($page as $key => $build_target) {
      $build_phid = $build_target->getBuildPHID();
      if (empty($builds[$build_phid])) {
        unset($page[$key]);
        continue;
      }
      $build_target->attachBuild($builds[$build_phid]);
    }

    return $page;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorHarbormasterApplication';
  }

}
