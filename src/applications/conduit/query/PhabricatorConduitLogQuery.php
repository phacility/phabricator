<?php

final class PhabricatorConduitLogQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $callerPHIDs;
  private $methods;
  private $methodStatuses;
  private $epochMin;
  private $epochMax;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withCallerPHIDs(array $phids) {
    $this->callerPHIDs = $phids;
    return $this;
  }

  public function withMethods(array $methods) {
    $this->methods = $methods;
    return $this;
  }

  public function withMethodStatuses(array $statuses) {
    $this->methodStatuses = $statuses;
    return $this;
  }

  public function withEpochBetween($epoch_min, $epoch_max) {
    $this->epochMin = $epoch_min;
    $this->epochMax = $epoch_max;
    return $this;
  }

  public function newResultObject() {
    return new PhabricatorConduitMethodCallLog();
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'id IN (%Ld)',
        $this->ids);
    }

    if ($this->callerPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'callerPHID IN (%Ls)',
        $this->callerPHIDs);
    }

    if ($this->methods !== null) {
      $where[] = qsprintf(
        $conn,
        'method IN (%Ls)',
        $this->methods);
    }

    if ($this->methodStatuses !== null) {
      $statuses = array_fuse($this->methodStatuses);

      $methods = id(new PhabricatorConduitMethodQuery())
        ->setViewer($this->getViewer())
        ->execute();

      $method_names = array();
      foreach ($methods as $method) {
        $status = $method->getMethodStatus();
        if (isset($statuses[$status])) {
          $method_names[] = $method->getAPIMethodName();
        }
      }

      if (!$method_names) {
        throw new PhabricatorEmptyQueryException();
      }

      $where[] = qsprintf(
        $conn,
        'method IN (%Ls)',
        $method_names);
    }

    if ($this->epochMin !== null) {
      $where[] = qsprintf(
        $conn,
        'dateCreated >= %d',
        $this->epochMin);
    }

    if ($this->epochMax !== null) {
      $where[] = qsprintf(
        $conn,
        'dateCreated <= %d',
        $this->epochMax);
    }

    return $where;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorConduitApplication';
  }

}
