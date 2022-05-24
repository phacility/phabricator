<?php

final class HarbormasterBuildTargetQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $buildPHIDs;
  private $buildGenerations;
  private $dateCreatedMin;
  private $dateCreatedMax;
  private $dateStartedMin;
  private $dateStartedMax;
  private $dateCompletedMin;
  private $dateCompletedMax;
  private $statuses;

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

  public function withDateCreatedBetween($min, $max) {
    $this->dateCreatedMin = $min;
    $this->dateCreatedMax = $max;
    return $this;
  }

  public function withDateStartedBetween($min, $max) {
    $this->dateStartedMin = $min;
    $this->dateStartedMax = $max;
    return $this;
  }

  public function withDateCompletedBetween($min, $max) {
    $this->dateCompletedMin = $min;
    $this->dateCompletedMax = $max;
    return $this;
  }

  public function withTargetStatuses(array $statuses) {
    $this->statuses = $statuses;
    return $this;
  }

  public function needBuildSteps($need_build_steps) {
    $this->needBuildSteps = $need_build_steps;
    return $this;
  }

  public function newResultObject() {
    return new HarbormasterBuildTarget();
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
        'phid in (%Ls)',
        $this->phids);
    }

    if ($this->buildPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'buildPHID in (%Ls)',
        $this->buildPHIDs);
    }

    if ($this->buildGenerations !== null) {
      $where[] = qsprintf(
        $conn,
        'buildGeneration in (%Ld)',
        $this->buildGenerations);
    }

    if ($this->dateCreatedMin !== null) {
      $where[] = qsprintf(
        $conn,
        'dateCreated >= %d',
        $this->dateCreatedMin);
    }

    if ($this->dateCreatedMax !== null) {
      $where[] = qsprintf(
        $conn,
        'dateCreated <= %d',
        $this->dateCreatedMax);
    }

    if ($this->dateStartedMin !== null) {
      $where[] = qsprintf(
        $conn,
        'dateStarted >= %d',
        $this->dateStartedMin);
    }

    if ($this->dateStartedMax !== null) {
      $where[] = qsprintf(
        $conn,
        'dateStarted <= %d',
        $this->dateStartedMax);
    }

    if ($this->dateCompletedMin !== null) {
      $where[] = qsprintf(
        $conn,
        'dateCompleted >= %d',
        $this->dateCompletedMin);
    }

    if ($this->dateCompletedMax !== null) {
      $where[] = qsprintf(
        $conn,
        'dateCompleted <= %d',
        $this->dateCompletedMax);
    }

    if ($this->statuses !== null) {
      $where[] = qsprintf(
        $conn,
        'targetStatus IN (%Ls)',
        $this->statuses);
    }

    return $where;
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
