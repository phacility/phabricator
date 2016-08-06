<?php

final class HarbormasterBuildQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $buildStatuses;
  private $buildablePHIDs;
  private $buildPlanPHIDs;
  private $initiatorPHIDs;
  private $needBuildTargets;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withBuildStatuses(array $build_statuses) {
    $this->buildStatuses = $build_statuses;
    return $this;
  }

  public function withBuildablePHIDs(array $buildable_phids) {
    $this->buildablePHIDs = $buildable_phids;
    return $this;
  }

  public function withBuildPlanPHIDs(array $build_plan_phids) {
    $this->buildPlanPHIDs = $build_plan_phids;
    return $this;
  }

  public function withInitiatorPHIDs(array $initiator_phids) {
    $this->initiatorPHIDs = $initiator_phids;
    return $this;
  }

  public function needBuildTargets($need_targets) {
    $this->needBuildTargets = $need_targets;
    return $this;
  }

  public function newResultObject() {
    return new HarbormasterBuild();
  }

  protected function loadPage() {
    return $this->loadStandardPage($this->newResultObject());
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

    foreach ($page as $key => $build) {
      $buildable_phid = $build->getBuildablePHID();
      if (empty($buildables[$buildable_phid])) {
        unset($page[$key]);
        continue;
      }
      $build->attachBuildable($buildables[$buildable_phid]);
    }

    return $page;
  }

  protected function didFilterPage(array $page) {
    $plans = array();

    $plan_phids = array_filter(mpull($page, 'getBuildPlanPHID'));
    if ($plan_phids) {
      $plans = id(new PhabricatorObjectQuery())
        ->setViewer($this->getViewer())
        ->withPHIDs($plan_phids)
        ->setParentQuery($this)
        ->execute();
      $plans = mpull($plans, null, 'getPHID');
    }

    foreach ($page as $key => $build) {
      $plan_phid = $build->getBuildPlanPHID();
      $build->attachBuildPlan(idx($plans, $plan_phid));
    }

    $build_phids = mpull($page, 'getPHID');
    $commands = id(new HarbormasterBuildCommand())->loadAllWhere(
      'targetPHID IN (%Ls) ORDER BY id ASC',
      $build_phids);
    $commands = mgroup($commands, 'getTargetPHID');
    foreach ($page as $build) {
      $unprocessed_commands = idx($commands, $build->getPHID(), array());
      $build->attachUnprocessedCommands($unprocessed_commands);
    }

    if ($this->needBuildTargets) {
      $targets = id(new HarbormasterBuildTargetQuery())
        ->setViewer($this->getViewer())
        ->setParentQuery($this)
        ->withBuildPHIDs($build_phids)
        ->execute();

      // TODO: Some day, when targets have dependencies, we should toposort
      // these. For now, just put them into chronological order.
      $targets = array_reverse($targets);

      $targets = mgroup($targets, 'getBuildPHID');
      foreach ($page as $build) {
        $build_targets = idx($targets, $build->getPHID(), array());

        foreach ($build_targets as $phid => $target) {
          if ($target->getBuildGeneration() !== $build->getBuildGeneration()) {
            unset($build_targets[$phid]);
          }
        }

        $build->attachBuildTargets($build_targets);
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
        'phid in (%Ls)',
        $this->phids);
    }

    if ($this->buildStatuses !== null) {
      $where[] = qsprintf(
        $conn,
        'buildStatus in (%Ls)',
        $this->buildStatuses);
    }

    if ($this->buildablePHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'buildablePHID IN (%Ls)',
        $this->buildablePHIDs);
    }

    if ($this->buildPlanPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'buildPlanPHID IN (%Ls)',
        $this->buildPlanPHIDs);
    }

    if ($this->initiatorPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'initiatorPHID IN (%Ls)',
        $this->initiatorPHIDs);
    }

    return $where;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorHarbormasterApplication';
  }

}
