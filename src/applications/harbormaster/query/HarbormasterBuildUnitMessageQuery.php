<?php

final class HarbormasterBuildUnitMessageQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $targetPHIDs;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withBuildTargetPHIDs(array $target_phids) {
    $this->targetPHIDs = $target_phids;
    return $this;
  }

  public function newResultObject() {
    return new HarbormasterBuildUnitMessage();
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

    if ($this->targetPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'buildTargetPHID in (%Ls)',
        $this->targetPHIDs);
    }

    return $where;
  }

  protected function didFilterPage(array $messages) {
    $indexes = array();
    foreach ($messages as $message) {
      $index = $message->getNameIndex();
      if (strlen($index)) {
        $indexes[$index] = $index;
      }
    }

    if ($indexes) {
      $map = HarbormasterString::newIndexMap($indexes);

      foreach ($messages as $message) {
        $index = $message->getNameIndex();

        if (!strlen($index)) {
          continue;
        }

        $name = idx($map, $index);
        if ($name === null) {
          $name = pht('Unknown Unit Message ("%s")', $index);
        }

        $message->setName($name);
      }
    }

    return $messages;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorHarbormasterApplication';
  }

}
