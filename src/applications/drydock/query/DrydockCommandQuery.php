<?php

final class DrydockCommandQuery extends DrydockQuery {

  private $ids;
  private $targetPHIDs;
  private $consumed;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withTargetPHIDs(array $phids) {
    $this->targetPHIDs = $phids;
    return $this;
  }

  public function withConsumed($consumed) {
    $this->consumed = $consumed;
    return $this;
  }

  public function newResultObject() {
    return new DrydockCommand();
  }

  protected function willFilterPage(array $commands) {
    $target_phids = mpull($commands, 'getTargetPHID');

    $targets = id(new PhabricatorObjectQuery())
      ->setViewer($this->getViewer())
      ->setParentQuery($this)
      ->withPHIDs($target_phids)
      ->execute();
    $targets = mpull($targets, null, 'getPHID');

    foreach ($commands as $key => $command) {
      $target = idx($targets, $command->getTargetPHID());
      if (!$target) {
        $this->didRejectResult($command);
        unset($commands[$key]);
        continue;
      }
      $command->attachCommandTarget($target);
    }

    return $commands;
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'id IN (%Ld)',
        $this->ids);
    }

    if ($this->targetPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'targetPHID IN (%Ls)',
        $this->targetPHIDs);
    }

    if ($this->consumed !== null) {
      $where[] = qsprintf(
        $conn,
        'isConsumed = %d',
        (int)$this->consumed);
    }

    return $where;
  }

}
