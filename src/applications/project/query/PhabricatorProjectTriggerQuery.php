<?php

final class PhabricatorProjectTriggerQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $activeColumnMin;
  private $activeColumnMax;

  private $needUsage;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function needUsage($need_usage) {
    $this->needUsage = $need_usage;
    return $this;
  }

  public function withActiveColumnCountBetween($min, $max) {
    $this->activeColumnMin = $min;
    $this->activeColumnMax = $max;
    return $this;
  }

  public function newResultObject() {
    return new PhabricatorProjectTrigger();
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'trigger.id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn,
        'trigger.phid IN (%Ls)',
        $this->phids);
    }

    if ($this->activeColumnMin !== null) {
      $where[] = qsprintf(
        $conn,
        'trigger_usage.activeColumnCount >= %d',
        $this->activeColumnMin);
    }

    if ($this->activeColumnMax !== null) {
      $where[] = qsprintf(
        $conn,
        'trigger_usage.activeColumnCount <= %d',
        $this->activeColumnMax);
    }

    return $where;
  }

  protected function buildJoinClauseParts(AphrontDatabaseConnection $conn) {
    $joins = parent::buildJoinClauseParts($conn);

    if ($this->shouldJoinUsageTable()) {
      $joins[] = qsprintf(
        $conn,
        'JOIN %R trigger_usage ON trigger.phid = trigger_usage.triggerPHID',
        new PhabricatorProjectTriggerUsage());
    }

    return $joins;
  }

  private function shouldJoinUsageTable() {
    if ($this->activeColumnMin !== null) {
      return true;
    }

    if ($this->activeColumnMax !== null) {
      return true;
    }

    return false;
  }

  protected function didFilterPage(array $triggers) {
    if ($this->needUsage) {
      $usage_map = id(new PhabricatorProjectTriggerUsage())->loadAllWhere(
        'triggerPHID IN (%Ls)',
        mpull($triggers, 'getPHID'));
      $usage_map = mpull($usage_map, null, 'getTriggerPHID');

      foreach ($triggers as $trigger) {
        $trigger_phid = $trigger->getPHID();

        $usage = idx($usage_map, $trigger_phid);
        if (!$usage) {
          $usage = id(new PhabricatorProjectTriggerUsage())
            ->setTriggerPHID($trigger_phid)
            ->setExamplePHID(null)
            ->setColumnCount(0)
            ->setActiveColumnCount(0);
        }

        $trigger->attachUsage($usage);
      }
    }

    return $triggers;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorProjectApplication';
  }

  protected function getPrimaryTableAlias() {
    return 'trigger';
  }

}
